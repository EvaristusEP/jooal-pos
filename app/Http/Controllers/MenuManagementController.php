<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuVariant;
use App\Models\Tag;
use App\Services\BranchContext;
use App\Services\RecipeStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuManagementController extends Controller
{
    public function index(RecipeStockService $recipeStock, BranchContext $branchContext): View
    {
        $menus = Menu::query()->with(['category', 'variants', 'branches', 'tags'])->withCount('variants')->orderBy('name')->get();
        $activeBranch = $branchContext->activeFor(request()->user());
        $menus->each(function (Menu $menu) use ($recipeStock, $activeBranch) {
            $menu->setAttribute('recipe_ready', $recipeStock->menuRecipeReady($menu));
            if ($activeBranch) $menu->setAttribute('availability', $recipeStock->availability($menu, $activeBranch));
        });
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();
        $branches = Branch::query()->orderBy('name')->get();
        $tags = Tag::query()->orderBy('name')->get();

        return view('master-menu.index', compact('menus', 'categories', 'branches', 'tags'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedMenu($request);
        $validated['is_active'] = false;
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);

        $menu = DB::transaction(function () use ($validated, $request) {
            $menu = Menu::create($validated);
            $this->syncVariants($request, $menu);
            $this->syncBranchSettings($request, $menu);
            $this->syncTags($request, $menu);

            return $menu;
        });

        return redirect()->route('master-menu.edit', $menu)->with('success', 'Menu berhasil dibuat.');
    }

    public function edit(Menu $menu): View
    {
        $menu->load(['category', 'variants', 'branches', 'tags']);
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();
        $branches = Branch::query()->orderBy('name')->get();
        $tags = Tag::query()->orderBy('name')->get();

        return view('master-menu.edit', compact('menu', 'categories', 'branches', 'tags'));
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        try {
            $validated = $this->validatedMenu($request, $menu);
        } catch (ValidationException $exception) {
            return redirect()->route('master-menu.index', ['edit_menu' => $menu->getKey()])
                ->withErrors($exception->errors())
                ->withInput();
        }
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);

        DB::transaction(function () use ($validated, $request, $menu) {
            $locked = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            $locked->update($validated);
            $this->syncVariants($request, $locked);
            $this->syncBranchSettings($request, $locked);
            $this->syncTags($request, $locked);
        });

        return redirect()->route('master-menu.index')->with('success', 'Menu berhasil diperbarui.');
    }

    public function toggleStatus(Menu $menu, RecipeStockService $recipeStock): RedirectResponse
    {
        DB::transaction(function () use ($menu, $recipeStock) {
            $locked = Menu::query()->whereKey($menu->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->is_active && ! $recipeStock->menuRecipeReady($locked)) {
                throw ValidationException::withMessages(['recipe' => 'Produk belum dapat diaktifkan. Lengkapi resep untuk semua varian yang dijual terlebih dahulu.']);
            }
            $locked->update(['is_active' => ! $locked->is_active]);
        });
        $menu->refresh();

        return redirect()->route('master-menu.index')->with('success', $menu->is_active
            ? 'Menu berhasil diaktifkan.'
            : 'Menu berhasil dinonaktifkan.');
    }

    private function validatedMenu(Request $request, ?Menu $menu = null): array
    {
        $slugRule = Rule::unique('menus', 'slug');
        if ($menu) {
            $slugRule = $slugRule->ignore($menu->getKey());
        }

        return $request->validate([
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', $slugRule],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['nullable', 'string', 'max:100'],
            'variants.*.price_adjustment' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'branch_settings' => ['nullable', 'array'],
            'branch_settings.*.price' => ['nullable', 'numeric', 'min:0'],
            'branch_settings.*.is_available' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')],
        ]);
    }

    private function syncTags(Request $request, Menu $menu): void
    {
        if ($request->has('sync_tag_ids')) {
            $menu->tags()->sync($request->input('tag_ids', []));
        }
    }

    private function syncVariants(Request $request, Menu $menu): void
    {
        foreach ($request->input('variants', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $variant = ! empty($row['id'])
                ? $menu->variants()->whereKey((int) $row['id'])->first()
                : new MenuVariant();

            if (! $variant) {
                abort(422, 'Varian menu tidak valid.');
            }

            $variant->menu_id = $menu->getKey();
            $variant->name = $name;
            $variant->slug = $menu->slug.'-'.Str::slug($name).($variant->exists ? '-'.$variant->getKey() : '');
            $variant->price_adjustment = (float) ($row['price_adjustment'] ?? 0);
            $variant->is_active = (bool) ($row['is_active'] ?? false);
            $variant->save();
        }
    }

    private function syncBranchSettings(Request $request, Menu $menu): void
    {
        $settings = $request->input('branch_settings', []);
        if (! $request->has('branch_settings')) {
            return;
        }
        $pivot = [];

        foreach (Branch::query()->pluck('id') as $branchId) {
            $setting = $settings[$branchId] ?? [];
            $pivot[$branchId] = [
                'price' => ($setting['price'] ?? '') === '' ? null : (float) $setting['price'],
                'is_available' => (bool) ($setting['is_available'] ?? false),
            ];
        }

        $menu->branches()->sync($pivot);
    }
}
