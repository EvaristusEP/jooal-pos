<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\User;
use Database\Seeders\NusaBrewDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class NusaBrewDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_in_nusa_demo_seeder_is_idempotent_and_keeps_receipt_ledger_and_branch_balance_consistent(): void
    {
        User::factory()->create(['role' => User::ROLE_OWNER]);

        $seeder = new NusaBrewDemoSeeder();
        $seeder->run();
        $seeder->run();

        $branch = Branch::query()->where('code', 'JOOAL-003')->firstOrFail();
        $this->assertSame('Nusa Brew & Toast', $branch->name);
        $this->assertSame(1, Branch::query()->where('code', 'JOOAL-003')->count());
        $this->assertSame(1, StockReceipt::query()->where('receipt_number', 'DEMO-NUSA-OPEN-001')->count());
        $this->assertSame(9, StockMovement::query()->where('branch_id', $branch->id)->where('type', 'stock_in')->count());
        $this->assertSame(9, BranchStock::query()->where('branch_id', $branch->id)->count());
        $this->assertSame(3, $branch->menus()->wherePivot('is_available', true)->count());
        $this->assertSame(13, RecipeItem::query()->where('is_active', true)->count());
        $this->assertDatabaseHas('menus', ['slug' => 'demo-nusa-kopi-kelapa', 'uses_recipe' => 1, 'is_active' => 1]);
        $this->assertDatabaseHas('menus', ['slug' => 'demo-nusa-oat-aren-latte', 'uses_recipe' => 1, 'is_active' => 1]);
        $this->assertDatabaseHas('menus', ['slug' => 'demo-nusa-kaya-butter-toast', 'uses_recipe' => 1, 'is_active' => 1]);
        $this->assertDatabaseHas('coupons', ['code' => 'NUSADEMO10', 'is_active' => 1, 'usage_limit' => 50]);
        foreach (StockMovement::query()->where('branch_id', $branch->id)->get() as $movement) {
            $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $movement->ingredient_id)->firstOrFail();
            $this->assertSame((string) $movement->quantity_after, (string) $stock->quantity);
        }
    }

    public function test_conflicting_existing_demo_branch_is_left_unchanged_and_seeding_aborts(): void
    {
        User::factory()->create(['role' => User::ROLE_OWNER]);
        $branch = Branch::create(['code' => 'JOOAL-003', 'name' => 'Existing Business Branch', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);

        try {
            (new NusaBrewDemoSeeder())->run();
            $this->fail('Expected branch conflict to abort the demo seed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('differs from the active Nusa Brew', $exception->getMessage());
        }

        $this->assertSame('Existing Business Branch', $branch->fresh()->name);
        $this->assertDatabaseMissing('ingredients', ['slug' => 'demo-nusa-coffee-beans']);
    }
}
