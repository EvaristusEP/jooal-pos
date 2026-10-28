<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use App\Models\Topping;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Opt-in idempotent fixture. Never register this seeder in DatabaseSeeder. */
class NusaBrewDemoSeeder extends Seeder
{
    private const INGREDIENTS = [
        'coffee' => ['DEMO NUSA - Biji Kopi', 'demo-nusa-coffee-beans', 'g', '12000.000'],
        'coconut' => ['DEMO NUSA - Santan Kelapa', 'demo-nusa-coconut-milk', 'ml', '6000.000'],
        'oat' => ['DEMO NUSA - Susu Oat', 'demo-nusa-oat-milk', 'ml', '8000.000'],
        'palm' => ['DEMO NUSA - Sirup Gula Aren', 'demo-nusa-palm-syrup', 'ml', '3000.000'],
        'bread' => ['DEMO NUSA - Roti Tawar', 'demo-nusa-bread', 'pcs', '120.000'],
        'butter' => ['DEMO NUSA - Butter', 'demo-nusa-butter', 'g', '5000.000'],
        'kaya' => ['DEMO NUSA - Selai Kaya', 'demo-nusa-kaya-jam', 'g', '3000.000'],
        'cup' => ['DEMO NUSA - Cup', 'demo-nusa-cup', 'pcs', '120.000'],
        'straw' => ['DEMO NUSA - Pipet', 'demo-nusa-straw', 'pcs', '120.000'],
    ];

    public function run(): void
    {
        foreach (['branches', 'menus', 'branch_menu', 'ingredients', 'recipe_items', 'branch_stocks', 'stock_movements', 'stock_receipts', 'stock_receipt_items', 'suppliers', 'dining_tables', 'customers', 'toppings'] as $table) {
            if (! Schema::hasTable($table)) throw new RuntimeException("Nusa Brew demo requires table {$table}; no data was seeded.");
        }
        if (! Schema::hasColumn('menus', 'uses_recipe') || ! Schema::hasColumn('toppings', 'uses_recipe') || ! Schema::hasColumn('stock_movements', 'reference_key')) {
            throw new RuntimeException('Nusa Brew demo requires recipe tracking and stock movement reference migrations. Review pending migrations before seeding.');
        }

        DB::transaction(function (): void {
            $owner = User::query()->where('role', User::ROLE_OWNER)->orderBy('id')->first();
            if (! $owner) throw new RuntimeException('Nusa Brew demo requires an existing Owner account; it will not create credentials.');

            $branch = Branch::query()->firstOrCreate(['code' => 'JOOAL-003'], [
                'name' => 'Nusa Brew & Toast', 'timezone' => 'Asia/Jakarta', 'is_active' => true,
            ]);
            if ($branch->name !== 'Nusa Brew & Toast' || ! $branch->is_active) {
                throw new RuntimeException('JOOAL-003 exists but differs from the active Nusa Brew & Toast demo branch; existing branch data was left unchanged.');
            }

            $category = Category::query()->firstOrCreate(['slug' => 'demo-nusa-brew'], [
                'name' => 'Nusa Brew & Toast', 'sort_order' => 90, 'is_active' => true,
            ]);
            if ($category->name !== 'Nusa Brew & Toast' || ! $category->is_active) throw new RuntimeException('Nusa demo category slug conflicts with existing data.');

            $ingredients = collect(self::INGREDIENTS)->map(function (array $data) {
                $ingredient = Ingredient::query()->firstOrCreate(['slug' => $data[1]], [
                    'name' => $data[0], 'unit' => $data[2], 'minimum_stock' => 0, 'is_active' => true,
                ]);
                if ($ingredient->name !== $data[0] || $ingredient->unit !== $data[2] || ! $ingredient->is_active) {
                    throw new RuntimeException('Demo ingredient '.$data[1].' conflicts with its expected name, unit, or active state.');
                }
                return $ingredient;
            });

            $supplier = Supplier::query()->firstOrCreate(['name' => 'DEMO NUSA - Pemasok Bahan'], [
                'contact_name' => 'Tim Nusa Demo', 'phone' => '0000000003', 'email' => 'nusa-supplier@example.test',
                'address' => 'Data demonstrasi Nusa Brew', 'notes' => 'Pemasok fiktif untuk demo.', 'is_active' => true,
            ]);
            if (! $supplier->is_active || $supplier->email !== 'nusa-supplier@example.test') throw new RuntimeException('Nusa demo supplier conflicts with existing supplier data.');

            if (Schema::hasTable('coupons')) {
                $coupon = Coupon::query()->firstOrCreate(['code' => 'NUSADEMO10'], [
                    'discount_type' => 'percentage', 'discount_value' => '10.00',
                    'starts_at' => now()->subDay(), 'ends_at' => now()->addYear(),
                    'usage_limit' => 50, 'used_count' => 0, 'is_active' => true,
                ]);
                if ($coupon->discount_type !== 'percentage' || (string) $coupon->discount_value !== '10.00'
                    || (int) $coupon->usage_limit !== 50 || ! $coupon->is_active || $coupon->starts_at->isFuture()
                    || $coupon->ends_at->isPast() || $coupon->used_count >= $coupon->usage_limit) {
                    throw new RuntimeException('NUSADEMO10 exists but is not a valid Nusa demo coupon; existing coupon data was left unchanged.');
                }
            }

            $products = [
                'kelapa' => ['DEMO NUSA - Kopi Kelapa', 'demo-nusa-kopi-kelapa', 'Espresso, santan kelapa, cup, dan pipet.', 29000, [['coffee', '18.000', 'g'], ['coconut', '120.000', 'ml'], ['cup', '1.000', 'pcs'], ['straw', '1.000', 'pcs']]],
                'aren' => ['DEMO NUSA - Oat Aren Latte', 'demo-nusa-oat-aren-latte', 'Espresso, susu oat, sirup gula aren, cup, dan pipet.', 32000, [['coffee', '18.000', 'g'], ['oat', '150.000', 'ml'], ['palm', '25.000', 'ml'], ['cup', '1.000', 'pcs'], ['straw', '1.000', 'pcs']]],
                'toast' => ['DEMO NUSA - Kaya Butter Toast', 'demo-nusa-kaya-butter-toast', 'Roti panggang dengan butter dan selai kaya.', 24000, [['bread', '2.000', 'pcs'], ['butter', '12.000', 'g'], ['kaya', '25.000', 'g']]],
            ];
            $menus = [];
            foreach ($products as $key => [$name, $slug, $description, $price, $recipe]) {
                $menu = Menu::query()->firstOrCreate(['slug' => $slug], [
                    'category_id' => $category->id, 'name' => $name, 'description' => $description,
                    'price' => $price, 'is_active' => true,
                ]);
                if ((int) $menu->category_id !== (int) $category->id || $menu->name !== $name || $menu->description !== $description || (float) $menu->price !== (float) $price || ! $menu->is_active) {
                    throw new RuntimeException("Product {$slug} conflicts with the Nusa demo definition.");
                }
                DB::table('menus')->where('id', $menu->id)->update(['uses_recipe' => true]);
                foreach ($recipe as [$ingredientKey, $quantity, $unit]) $this->ensureRecipe($menu->id, null, $ingredients[$ingredientKey]->id, $quantity, $unit);
                $this->assertRecipeSet($menu->id, null, $recipe, $ingredients);
                $pivot = DB::table('branch_menu')->where('branch_id', $branch->id)->where('menu_id', $menu->id)->first();
                if ($pivot) {
                    if ((float) $pivot->price !== (float) $price || ! (bool) $pivot->is_available) throw new RuntimeException("Existing price/availability for {$slug} conflicts; it was not overwritten.");
                } else {
                    DB::table('branch_menu')->insert(['branch_id' => $branch->id, 'menu_id' => $menu->id, 'price' => $price, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                $menus[$key] = $menu;
            }

            $addon = Topping::query()->firstOrCreate(['slug' => 'demo-nusa-extra-shot'], ['name' => 'DEMO NUSA - Extra Shot', 'price' => 6000, 'is_active' => true]);
            if ($addon->name !== 'DEMO NUSA - Extra Shot' || (float) $addon->price !== 6000.0 || ! $addon->is_active) throw new RuntimeException('Nusa demo add-on conflicts with existing data.');
            DB::table('toppings')->where('id', $addon->id)->update(['uses_recipe' => true]);
            $this->ensureRecipe(null, $addon->id, $ingredients['coffee']->id, '18.000', 'g');
            if (RecipeItem::query()->where('topping_id', $addon->id)->whereNull('menu_id')->where('is_active', true)->count() !== 1) {
                throw new RuntimeException('Nusa demo add-on has extra or duplicate active recipes.');
            }

            $table = DiningTable::query()->firstOrCreate(['branch_id' => $branch->id, 'name' => 'NUSA-01'], ['capacity' => 2, 'is_active' => true]);
            if (! $table->is_active || (int) $table->capacity !== 2) throw new RuntimeException('NUSA-01 table conflicts with the expected demo table.');
            $customer = Customer::query()->firstOrCreate(['branch_id' => $branch->id, 'name' => 'DEMO NUSA - Pelanggan'], ['notes' => 'Pelanggan fiktif untuk presentasi.', 'is_active' => true]);
            if (! $customer->is_active) throw new RuntimeException('Nusa demo customer exists but is inactive.');

            $this->receiveOpeningStock($branch, $supplier, $owner, $ingredients);
        }, 3);
    }

    private function ensureRecipe(?int $menuId, ?int $toppingId, int $ingredientId, string $quantity, string $unit): void
    {
        $scope = ['menu_id' => $menuId, 'menu_variant_id' => null, 'size_id' => null, 'topping_id' => $toppingId, 'ingredient_id' => $ingredientId];
        $query = RecipeItem::query()->where($scope);
        if ($query->count() > 1) throw new RuntimeException('Duplicate Nusa demo recipe rows found; existing rows were not changed.');
        $recipe = $query->first();
        if (! $recipe) {
            RecipeItem::create($scope + ['quantity' => $quantity, 'unit' => $unit, 'is_active' => true]);
        } elseif (! $recipe->is_active || (string) $recipe->quantity !== $quantity || $recipe->unit !== $unit) {
            throw new RuntimeException('An existing Nusa demo recipe conflicts with its expected quantity or unit.');
        }
    }

    private function assertRecipeSet(int $menuId, ?int $toppingId, array $definitions, $ingredients): void
    {
        $count = RecipeItem::query()->where('menu_id', $menuId)->whereNull('topping_id')->whereNull('menu_variant_id')->whereNull('size_id')->where('is_active', true)->count();
        if ($count !== count($definitions)) throw new RuntimeException('Nusa demo product has an incomplete or extra active recipe.');
    }

    private function receiveOpeningStock(Branch $branch, Supplier $supplier, User $owner, $ingredients): void
    {
        $number = 'DEMO-NUSA-OPEN-001';
        $receipt = StockReceipt::query()->with('items.movement')->where('receipt_number', $number)->first();
        if ($receipt) {
            if ((int) $receipt->branch_id !== (int) $branch->id || (int) $receipt->supplier_id !== (int) $supplier->id || $receipt->items->count() !== count(self::INGREDIENTS)) {
                throw new RuntimeException('Existing Nusa opening receipt conflicts with expected demo records.');
            }
            foreach (self::INGREDIENTS as $key => $data) {
                $line = $receipt->items->firstWhere('ingredient_id', $ingredients[$key]->id);
                $movement = optional($line)->movement;
                if (! $line || (string) $line->quantity !== $data[3] || ! $movement || $movement->reference_key !== 'demo:nusa:opening:'.$ingredients[$key]->id
                    || (int) $movement->branch_id !== (int) $branch->id || (int) $movement->ingredient_id !== (int) $ingredients[$key]->id
                    || $movement->type !== 'stock_in' || (string) $movement->quantity_delta !== $data[3]
                    || (string) $movement->quantity_before !== '0.000' || (string) $movement->quantity_after !== $data[3]
                    || (float) BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredients[$key]->id)->value('quantity') !== (float) $data[3]) {
                    throw new RuntimeException('Nusa opening receipt or stock movement is inconsistent; no balances were changed.');
                }
            }
            return;
        }

        foreach (self::INGREDIENTS as $key => $data) {
            if (StockMovement::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredients[$key]->id)->exists()) {
                throw new RuntimeException('Existing stock history for '.$data[1].' at JOOAL-003 conflicts with demo opening stock.');
            }
            $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredients[$key]->id)->first();
            if ($stock && (float) $stock->quantity !== 0.0) throw new RuntimeException('Existing non-zero stock for '.$data[1].' at JOOAL-003 conflicts with demo opening stock.');
        }

        $receipt = StockReceipt::create(['receipt_number' => $number, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id,
            'received_by' => $owner->id, 'reason' => 'DEMO NUSA opening stock; data presentasi.', 'received_at' => now()]);
        foreach (self::INGREDIENTS as $key => $data) {
            $ingredient = $ingredients[$key];
            $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->lockForUpdate()->first();
            if (! $stock) $stock = BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => '0.000']);
            $stock->update(['quantity' => $data[3]]);
            $movement = StockMovement::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'user_id' => $owner->id,
                'type' => 'stock_in', 'quantity_delta' => $data[3], 'quantity_before' => '0.000', 'quantity_after' => $data[3],
                'reference_key' => 'demo:nusa:opening:'.$ingredient->id, 'reason' => 'DEMO NUSA opening stock']);
            StockReceiptItem::create(['stock_receipt_id' => $receipt->id, 'ingredient_id' => $ingredient->id, 'stock_movement_id' => $movement->id, 'quantity' => $data[3]]);
        }
    }
}
