<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\Topping;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Central recipe and per-branch availability rules. Quantities are kept as integer thousandths. */
class RecipeStockService
{
    public function canConvertUnits(string $from, string $to): bool
    {
        return $this->conversionRatio($from, $to) !== null;
    }

    public function recipeFingerprintForItem(array $item): string
    {
        $toppingIds = array_values(array_unique(array_map('intval', $item['topping_ids'] ?? [])));
        $rows = RecipeItem::query()->with('ingredient')->where(function ($query) use ($item, $toppingIds) {
            $query->where('menu_id', (int) ($item['menu_id'] ?? 0));
            if ($toppingIds) $query->orWhereIn('topping_id', $toppingIds);
        })->orderBy('id')->get()->map(function (RecipeItem $recipe) {
            return [
                'id' => (int) $recipe->id, 'menu_id' => $recipe->menu_id ? (int) $recipe->menu_id : null,
                'menu_variant_id' => $recipe->menu_variant_id ? (int) $recipe->menu_variant_id : null,
                'size_id' => $recipe->size_id ? (int) $recipe->size_id : null,
                'topping_id' => $recipe->topping_id ? (int) $recipe->topping_id : null,
                'ingredient_id' => (int) $recipe->ingredient_id,
                'quantity' => (string) $recipe->quantity, 'unit' => $recipe->unit,
                'is_active' => (bool) $recipe->is_active,
                'ingredient_unit' => optional($recipe->ingredient)->unit,
                'ingredient_active' => optional($recipe->ingredient)->is_active,
            ];
        })->all();
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function menuRecipeReady(Menu $menu): bool
    {
        $variants = $menu->variants()->where('is_active', true)->get();
        $recipes = RecipeItem::query()->where('menu_id', $menu->id)->whereNull('topping_id')->where('is_active', true)->get();
        if ($recipes->isEmpty()) return false;

        $base = $recipes->whereNull('menu_variant_id')->whereNull('size_id');
        if ($base->isNotEmpty() && ! $this->scopeIsValid($base)) return false;
        if ($variants->isEmpty()) return $base->isNotEmpty();
        foreach ($variants as $variant) {
            $scope = $recipes->where('menu_variant_id', $variant->id)->whereNull('size_id');
            if ($scope->isEmpty()) $scope = $base;
            if ($scope->isEmpty() || ! $this->scopeIsValid($scope)) return false;
        }
        return true;
    }

    public function requirementsForCart(Collection $cart): Collection
    {
        $totals = [];
        // Acquire recipe-parent locks in a stable order shared with recipe edits.
        Menu::query()->whereIn('id', $cart->pluck('menu_id')->map(fn ($id) => (int) $id)->unique()->sort()->values())
            ->orderBy('id')->lockForUpdate()->get();
        $toppingIds = $cart->flatMap(fn ($item) => $item['topping_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values();
        if ($toppingIds->isNotEmpty()) Topping::query()->whereIn('id', $toppingIds)->orderBy('id')->lockForUpdate()->get();
        foreach ($cart as $item) {
            $menu = Menu::query()->whereKey((int) ($item['menu_id'] ?? 0))->lockForUpdate()->firstOrFail();
            if (! $this->menuRecipeReady($menu)) {
                throw ValidationException::withMessages(['cart' => 'Resep menu '.$menu->name.' belum lengkap. Lengkapi resep sebelum produk dijual.']);
            }
            $recipes = RecipeItem::query()->with('ingredient')->where('menu_id', $menu->id)
                ->whereNull('topping_id')->where('is_active', true)->lockForUpdate()->get();
            $scope = $this->selectScope($recipes, $item);
            if ($scope->isEmpty() || ! $this->scopeIsValid($scope)) {
                throw ValidationException::withMessages(['cart' => 'Resep untuk pilihan menu '.$menu->name.' belum dikonfigurasi.']);
            }
            $this->addRecipes($totals, $scope, (int) $item['quantity']);

            foreach (array_unique(array_map('intval', $item['topping_ids'] ?? [])) as $toppingId) {
                $topping = Topping::query()->whereKey($toppingId)->firstOrFail();
                // A topping consumes stock only when a recipe is explicitly configured for it.
                if (! RecipeItem::query()->where('topping_id', $toppingId)->whereNull('menu_id')->exists()) continue;
                $recipes = RecipeItem::query()->with('ingredient')->where('topping_id', $toppingId)
                    ->whereNull('menu_id')->where('is_active', true)->lockForUpdate()->get();
                if ($recipes->isEmpty() || ! $this->scopeIsValid($recipes)) {
                    throw ValidationException::withMessages(['cart' => 'Resep topping '.$topping->name.' belum lengkap.']);
                }
                $this->addRecipes($totals, $recipes, (int) $item['quantity']);
            }
        }

        return collect($totals)->sortKeys()->map(function (array $row) {
            $row['quantity_milli'] = $row['quantity_milli'];
            $row['quantity'] = $this->formatMilli($row['quantity_milli']);
            return $row;
        })->values();
    }

    public function maximumPortions(Menu $menu, Branch $branch): ?int
    {
        return $this->availability($menu, $branch)['available_portions'];
    }

    /** Availability is an estimate per menu, not a promise across competing products. */
    public function availability(Menu $menu, Branch $branch): array
    {
        if (! $menu->is_active) {
            return ['status' => 'inactive', 'available_portions' => null, 'limiting_ingredient' => null];
        }
        if (! $this->menuRecipeReady($menu)) {
            return ['status' => 'recipe_incomplete', 'available_portions' => null, 'limiting_ingredient' => null];
        }
        $recipes = RecipeItem::query()->with('ingredient')->where('menu_id', $menu->id)
            ->whereNull('topping_id')->whereNull('size_id')->where('is_active', true)->get();
        $scopes = [];
        $base = $recipes->whereNull('menu_variant_id');
        $variants = $menu->variants()->where('is_active', true)->get();
        if ($variants->isEmpty()) $scopes[] = $base;
        foreach ($variants as $variant) {
            $scope = $recipes->where('menu_variant_id', $variant->id);
            $scopes[] = $scope->isNotEmpty() ? $scope : $base;
        }
        $maxima = [];
        foreach ($scopes as $scope) {
            if ($scope->isEmpty()) return ['status' => 'recipe_incomplete', 'available_portions' => null, 'limiting_ingredient' => null];
            $requirements = [];
            $this->addRecipes($requirements, $scope, 1);
            $scopeMaximum = null;
            $scopeLimiter = null;
            foreach ($requirements as $requirement) {
                $stock = BranchStock::query()->where('branch_id', $branch->id)
                    ->where('ingredient_id', $requirement['ingredient']->id)->value('quantity');
                if ($stock === null || strpos((string) $stock, '-') === 0) {
                    return ['status' => 'stock_unavailable', 'available_portions' => null, 'limiting_ingredient' => $requirement['ingredient']];
                }
                $availableMilli = $this->toMilli((string) $stock);
                $possible = intdiv(max(0, $availableMilli), max(1, $requirement['quantity_milli']));
                if ($scopeMaximum === null || $possible < $scopeMaximum) {
                    $scopeLimiter = $requirement['ingredient'];
                }
                $scopeMaximum = $scopeMaximum === null ? $possible : min($scopeMaximum, $possible);
            }
            $maxima[] = ['portions' => $scopeMaximum ?? 0, 'limiter' => $scopeLimiter];
        }
        if (! $maxima) return ['status' => 'recipe_incomplete', 'available_portions' => null, 'limiting_ingredient' => null];
        $limiting = collect($maxima)->sortBy('portions')->first();
        $portions = max(0, (int) $limiting['portions']);
        return [
            'status' => $portions === 0 ? 'out_of_stock' : ($portions <= 10 ? 'low_stock' : 'available'),
            'available_portions' => $portions,
            'limiting_ingredient' => $limiting['limiter'],
        ];
    }

    public function hasEnoughStock(Collection $requirements, Branch $branch): bool
    {
        foreach ($requirements as $requirement) {
            $available = $this->toMilli((string) (BranchStock::query()->where('branch_id', $branch->id)
                ->where('ingredient_id', $requirement['ingredient']->id)->value('quantity') ?? '0'));
            if ($available < $requirement['quantity_milli']) return false;
        }
        return true;
    }

    public function toMilli(string $quantity): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', trim($quantity), $m)) return 0;
        return ((int) $m[1] * 1000) + (int) str_pad($m[2] ?? '', 3, '0');
    }

    public function formatMilli(int $milli): string
    {
        return intdiv($milli, 1000).'.'.str_pad((string) ($milli % 1000), 3, '0', STR_PAD_LEFT);
    }

    private function addRecipes(array &$totals, Collection $recipes, int $portions): void
    {
        foreach ($recipes as $recipe) {
            $ingredient = $recipe->ingredient;
            $ratio = $ingredient ? $this->conversionRatio($recipe->unit, $ingredient->unit) : null;
            if ($ratio === null) {
                throw ValidationException::withMessages(['cart' => 'Satuan resep '.$recipe->unit.' tidak dapat dikonversi ke satuan bahan '.optional($ingredient)->unit.'.']);
            }
            $recipeMilli = $this->toMilli((string) $recipe->quantity);
            $numerator = $recipeMilli * $ratio[0] * $portions;
            if ($numerator % $ratio[1] !== 0) {
                throw ValidationException::withMessages(['cart' => 'Kuantitas resep '.$ingredient->name.' tidak dapat direpresentasikan dengan presisi stok 3 desimal.']);
            }
            $neededMilli = intdiv($numerator, $ratio[1]);
            $id = $ingredient->getKey();
            if (! isset($totals[$id])) $totals[$id] = ['ingredient' => $ingredient, 'quantity_milli' => 0];
            $totals[$id]['quantity_milli'] += $neededMilli;
        }
    }

    private function scopeIsValid(Collection $recipes): bool
    {
        if ($recipes->isEmpty()) return false;
        foreach ($recipes as $recipe) {
            if (! $recipe->ingredient || ! $recipe->ingredient->is_active || $this->toMilli((string) $recipe->quantity) < 1
                || ! $this->canConvertUnits($recipe->unit, $recipe->ingredient->unit)) return false;
        }
        return true;
    }

    private function selectScope(Collection $recipes, array $item): Collection
    {
        $variantId = ! empty($item['menu_variant_id']) ? (int) $item['menu_variant_id'] : null;
        $sizeId = ! empty($item['size_id']) ? (int) $item['size_id'] : null;
        $scoped = $variantId !== null && $recipes->where('menu_variant_id', $variantId)->isNotEmpty()
            ? $recipes->where('menu_variant_id', $variantId) : $recipes->whereNull('menu_variant_id');
        return $sizeId !== null && $scoped->where('size_id', $sizeId)->isNotEmpty()
            ? $scoped->where('size_id', $sizeId)->values() : $scoped->whereNull('size_id')->values();
    }

    /** ratio from source units to stock units, as an exact integer fraction. */
    private function conversionRatio(string $from, string $to): ?array
    {
        $units = [
            'g' => ['mass', 1], 'gram' => ['mass', 1], 'grams' => ['mass', 1],
            'kg' => ['mass', 1000], 'kilogram' => ['mass', 1000], 'kilograms' => ['mass', 1000],
            'ml' => ['volume', 1], 'milliliter' => ['volume', 1], 'millilitre' => ['volume', 1],
            'l' => ['volume', 1000], 'liter' => ['volume', 1000], 'litre' => ['volume', 1000],
            'pc' => ['count', 1], 'pcs' => ['count', 1], 'piece' => ['count', 1], 'pieces' => ['count', 1], 'unit' => ['count', 1],
            'cup' => ['count', 1], 'cups' => ['count', 1], 'pipet' => ['count', 1], 'pipette' => ['count', 1], 'pipettes' => ['count', 1],
            'straw' => ['count', 1], 'straws' => ['count', 1], 'sedotan' => ['count', 1],
        ];
        $from = strtolower(trim($from)); $to = strtolower(trim($to));
        if ($from === $to && isset($units[$from])) return [1, 1];
        if (! isset($units[$from], $units[$to]) || $units[$from][0] !== $units[$to][0]) return null;
        $a = $units[$from][1]; $b = $units[$to][1];
        return $a >= $b ? [intdiv($a, $b), 1] : [1, intdiv($b, $a)];
    }
}
