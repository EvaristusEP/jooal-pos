<?php

namespace App\Http\Controllers;

use App\Models\Topping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AddonController extends Controller
{
    public function index(): View
    {
        $addons = Topping::query()->orderBy('name')->get();

        return view('addons.index', compact('addons'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $data['is_active'] = true;
        Topping::create($data);

        return redirect()->route('addons.index')->with('success', 'Addon berhasil ditambahkan.');
    }

    public function edit(Topping $topping): View
    {
        return view('addons.edit', ['addon' => $topping]);
    }

    public function update(Request $request, Topping $topping): RedirectResponse
    {
        $data = $this->validated($request, $topping);
        $data['slug'] = Str::slug($data['slug'] ?? $data['name']);
        $topping->update($data);

        return redirect()->route('addons.index')->with('success', 'Addon berhasil diperbarui.');
    }

    public function toggleStatus(Topping $topping): RedirectResponse
    {
        $topping->update(['is_active' => ! $topping->is_active]);

        return redirect()->route('addons.index')->with('success', 'Status addon diperbarui.');
    }

    private function validated(Request $request, ?Topping $topping = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('toppings', 'slug')->ignore(optional($topping)->id)],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
    }
}
