<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\Category;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_access_master_menu(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/master-menu')->assertOk()->assertSee('Master Menu');
        $category = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Coffee Latte', 'slug' => 'coffee-latte', 'price' => 25000, 'is_active' => true]);
        $this->actingAs($owner)->get('/master-menu/'.$menu->id.'/edit')->assertOk()->assertSee('Harga dan ketersediaan per cabang');
        $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]))->get('/master-menu')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_CASHIER]))->get('/master-menu')->assertForbidden();
    }

    public function test_master_menu_edit_compiles_conditional_attributes_without_leaking_blade(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Matcha', 'slug' => 'matcha', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Matcha Latte', 'slug' => 'matcha-latte', 'price' => 25000, 'is_active' => true]);

        $this->actingAs($owner)->get('/master-menu/'.$menu->id.'/edit')
            ->assertOk()
            ->assertSee('value="'.$category->id.'" selected', false)
            ->assertSee('value="1" checked', false)
            ->assertDontSee('@selected')
            ->assertDontSee('@checked');
    }

    public function test_master_menu_index_renders_edit_modal_and_validation_reopens_it(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Matcha', 'slug' => 'matcha-modal', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Modal Latte', 'slug' => 'modal-latte', 'price' => 25000, 'is_active' => true]);

        $this->actingAs($owner)->get('/master-menu')
            ->assertOk()
            ->assertSee('<dialog id="menu-create-dialog"', false)
            ->assertSee('data-filter-search="menu-list"', false)
            ->assertSee(route('master-menu.update', $menu), false)
            ->assertDontSee(route('master-menu.edit', $menu), false);

        $this->actingAs($owner)->put(route('master-menu.update', $menu), [
            'category_id' => $category->id,
            'name' => '',
            'slug' => $menu->slug,
            'price' => 25000,
            'description' => '',
            'is_active' => 1,
        ])->assertRedirect(route('master-menu.index', ['edit_menu' => $menu->id]))
            ->assertSessionHasErrors('name');
    }

    public function test_new_menu_is_inactive_and_cannot_be_activated_until_recipe_is_complete(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $category = Category::create(['name' => 'Coffee', 'slug' => 'coffee-new-menu', 'sort_order' => 1, 'is_active' => true]);
        $this->actingAs($owner)->post('/master-menu', [
            'category_id' => $category->id, 'name' => 'New Latte', 'slug' => 'new-latte', 'price' => 15000,
            'description' => '', 'is_active' => 1,
        ])->assertRedirect();
        $menu = Menu::where('slug', 'new-latte')->firstOrFail();
        $this->assertSame(0, (int) $menu->is_active);
        $this->actingAs($owner)->patch('/master-menu/'.$menu->id.'/toggle-status')
            ->assertSessionHasErrors('recipe');
        $this->assertSame(0, (int) $menu->fresh()->is_active);
    }

    public function test_catalog_uses_standard_price_fallback_and_branch_override(): void
    {
        [$cashier, $branch, $menu] = $this->cashierMenuContext();
        $this->addRecipeStock($branch, $menu);
        $branch->menus()->attach($menu, ['price' => 18000, 'is_available' => true]);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertSee('18.000');

        $menu->update(['price' => 22000]);
        $this->assertSame('18000', (string) $menu->branches()->first()->pivot->price);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertSee('18.000');

        $menu->branches()->updateExistingPivot($branch->id, ['price' => null]);
        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertSee('22.000');
    }

    public function test_unavailable_menu_is_hidden_and_rejected_by_backend(): void
    {
        [$cashier, $branch, $menu] = $this->cashierMenuContext();
        $branch->menus()->attach($menu, ['price' => null, 'is_available' => false]);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/dashboard/pos')->assertOk()->assertDontSee($menu->name);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->post('/dashboard/cart', ['menu_id' => $menu->id, 'quantity' => 1])
            ->assertSessionHasErrors('menu_id');
    }

    public function test_checkout_reprices_tampered_cart_and_saves_snapshot(): void
    {
        [$cashier, $branch, $menu] = $this->cashierMenuContext();
        $branch->menus()->attach($menu, ['price' => 17500, 'is_available' => true]);
        $ingredient = Ingredient::create(['name' => 'Coffee beans', 'slug' => 'coffee-beans', 'unit' => 'gram', 'minimum_stock' => 10, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => 10, 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 100]);

        $this->actingAs($cashier)->withSession([
            'active_branch_id' => $branch->id,
            'checkout_idempotency_key' => 'menu-checkout-snapshot-test',
            'pos_cart' => [[
                'menu_id' => $menu->id,
                'topping_ids' => [],
                'matcha_type_id' => null,
                'matcha_origin_id' => null,
                'menu_variant_id' => null,
                'quantity' => 2,
                'unit_price' => 1,
                'line_total' => 2,
                'category_slug' => 'non-matcha',
                'recipe_fingerprint' => app(\App\Services\RecipeStockService::class)->recipeFingerprintForItem(['menu_id' => $menu->id, 'topping_ids' => []]),
            ]],
        ])->post('/checkout', [
            'customer_name' => 'Customer',
            'service_type' => 'take_away',
            'pager_number' => 'P-01',
            'payment_method' => 'cash',
            'cash_amount' => 40000,
        ])->assertRedirect();

        $item = TransactionItem::query()->latest('id')->firstOrFail();
        $this->assertSame('17500.00', (string) $item->unit_price);
        $this->assertSame('17500.00', (string) $item->branch_price);
        $this->assertSame('25000.00', (string) $item->menu_price);
        $this->assertSame($menu->name, $item->menu_name);
        $this->assertSame('35000.00', (string) $item->line_total);
        $this->assertSame('17500', (string) $item->item_options['branch_price']);
    }

    private function cashierMenuContext(): array
    {
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $branch = Branch::create(['code' => 'JKT-01', 'name' => 'JOOAL Jakarta', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);
        $category = Category::create(['name' => 'Non Matcha', 'slug' => 'non-matcha', 'sort_order' => 2, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Coffee Latte', 'slug' => 'coffee-latte', 'description' => 'Coffee', 'price' => 25000, 'is_active' => true]);

        return [$cashier, $branch, $menu];
    }

    private function addRecipeStock(Branch $branch, Menu $menu): void
    {
        $ingredient = Ingredient::create(['name' => 'Coffee beans', 'slug' => 'catalog-beans', 'unit' => 'gram', 'minimum_stock' => 0, 'is_active' => true]);
        RecipeItem::create(['menu_id' => $menu->id, 'ingredient_id' => $ingredient->id, 'quantity' => '10.000', 'unit' => 'gram', 'is_active' => true]);
        BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => '100.000']);
    }
}
