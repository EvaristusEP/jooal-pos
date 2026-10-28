<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\RecipeItem;
use App\Models\Topping;
use App\Services\RecipeStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(): View
    {
        $recipes = RecipeItem::query()->with(['menu', 'variant', 'size', 'topping', 'ingredient'])->latest()->get();
        // Keep inactive products selectable so legacy products can be completed.
        $menus = Menu::query()->orderBy('name')->with('variants')->get();
        $toppings = Topping::query()->orderBy('name')->get();
        $ingredients = Ingredient::query()->where('is_active', true)->orderBy('name')->get();

        return view('recipes.index', compact('recipes', 'menus', 'toppings', 'ingredients'));
    }

    public function store(Request $request, RecipeStockService $recipeStock): RedirectResponse
    {
        $data = $this->validated($request, $recipeStock);
        DB::transaction(function () use ($data) {
            $this->lockParents($data);
            RecipeItem::create($data);
        });

        return redirect()->route('recipes.index')->with('success', 'Resep berhasil ditambahkan.');
    }

    public function update(Request $request, RecipeItem $recipeItem, RecipeStockService $recipeStock): RedirectResponse
    {
        $data = $this->validated($request, $recipeStock);
        DB::transaction(function () use ($data, $recipeItem) {
            $this->lockParents($data, $recipeItem);
            RecipeItem::query()->whereKey($recipeItem->getKey())->lockForUpdate()->firstOrFail()->update($data);
        });

        return redirect()->route('recipes.index')->with('success', 'Resep berhasil diperbarui.');
    }

    public function toggleStatus(RecipeItem $recipeItem): RedirectResponse
    {
        DB::transaction(function () use ($recipeItem) {
            $this->lockParents([], $recipeItem);
            $locked = RecipeItem::query()->whereKey($recipeItem->getKey())->lockForUpdate()->firstOrFail();
            $locked->update(['is_active' => ! $locked->is_active]);
        });

        return redirect()->route('recipes.index')->with('success', 'Status resep diperbarui.');
    }

    private function validated(Request $request, RecipeStockService $recipeStock): array
    {
        $data = $request->validate([
            'menu_id' => ['nullable', 'integer', 'exists:menus,id'],
            'menu_variant_id' => ['nullable', 'integer', 'exists:menu_variants,id'],
            'size_id' => ['nullable', 'integer', 'exists:sizes,id'],
            'topping_id' => ['nullable', 'integer', 'exists:toppings,id'],
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'unit' => ['required', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['unit'] = trim($data['unit']);
        $ingredient = Ingredient::query()->findOrFail($data['ingredient_id']);

        if (! $ingredient->is_active) {
            throw ValidationException::withMessages(['ingredient_id' => 'Bahan baku nonaktif tidak dapat dipakai dalam resep.']);
        }

        if ((empty($data['menu_id']) && empty($data['topping_id'])) || (! empty($data['menu_id']) && ! empty($data['topping_id']))) {
            throw ValidationException::withMessages(['menu_id' => 'Pilih tepat satu: menu atau topping.']);
        }
        if (! empty($data['menu_id']) && ! Menu::query()->whereKey($data['menu_id'])->exists()) {
            throw ValidationException::withMessages(['menu_id' => 'Menu tidak valid.']);
        }
        if (! empty($data['topping_id']) && ! Topping::query()->whereKey($data['topping_id'])->exists()) {
            throw ValidationException::withMessages(['topping_id' => 'Topping tidak valid.']);
        }
        if (! empty($data['topping_id']) && (! empty($data['menu_variant_id']) || ! empty($data['size_id']))) {
            throw ValidationException::withMessages(['topping_id' => 'Resep topping tidak menggunakan varian atau ukuran.']);
        }
        if (! empty($data['menu_variant_id'])) {
            $variant = \App\Models\MenuVariant::query()->findOrFail($data['menu_variant_id']);
            if ((int) $variant->menu_id !== (int) $data['menu_id']) {
                throw ValidationException::withMessages(['menu_variant_id' => 'Varian tidak berasal dari menu yang dipilih.']);
            }
        }
        if (! $recipeStock->canConvertUnits($data['unit'], (string) $ingredient->unit)) {
            throw ValidationException::withMessages(['unit' => 'Satuan resep harus sesuai dimensi bahan baku (massa, volume, atau hitungan).']);
        }

        return $data;
    }

    private function lockParents(array $data, ?RecipeItem $old = null): void
    {
        $menuIds = array_filter([(int) ($data['menu_id'] ?? 0), (int) optional($old)->menu_id]);
        $menuIds = array_values(array_unique($menuIds)); sort($menuIds);
        Menu::query()->whereIn('id', $menuIds)->orderBy('id')->lockForUpdate()->get();
        $toppingIds = array_filter([(int) ($data['topping_id'] ?? 0), (int) optional($old)->topping_id]);
        $toppingIds = array_values(array_unique($toppingIds)); sort($toppingIds);
        Topping::query()->whereIn('id', $toppingIds)->orderBy('id')->lockForUpdate()->get();
    }

}
