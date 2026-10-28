<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('branches.index', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $hasActiveBranch = Branch::query()->where('is_active', true)->exists();
        Branch::create($validated);

        return redirect()
            ->route($hasActiveBranch ? 'branches.index' : 'branches.select')
            ->with('success', 'Cabang berhasil ditambahkan.');
    }

    public function edit(Branch $branch): View
    {
        return view('branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $this->validatedData($request, $branch);
        if ($branch->transactions()->exists()) {
            if ($branch->is_active && ! $validated['is_active']) {
                return back()->withInput()->withErrors(['branch' => 'Cabang ini memiliki histori transaksi. Periksa dampak operasional dan laporan sebelum menonaktifkannya.']);
            }
            if ($validated['code'] !== $branch->code || $validated['name'] !== $branch->name || $validated['timezone'] !== $branch->timezone) {
                return back()->withInput()->withErrors(['branch' => 'Kode, nama, dan zona waktu cabang yang sudah memiliki transaksi tidak dapat diubah dari sini karena memengaruhi konteks histori.']);
            }
        }
        $branch->update($validated);

        return redirect()->route('branches.index')->with('success', 'Cabang berhasil diperbarui.');
    }

    public function toggleStatus(Branch $branch): RedirectResponse
    {
        if ($branch->is_active && $branch->transactions()->exists()) {
            return back()->withErrors(['branch' => 'Cabang ini memiliki histori transaksi. Periksa dampak operasional dan laporan sebelum menonaktifkannya.']);
        }
        $branch->update(['is_active' => ! $branch->is_active]);

        return redirect()->route('branches.index')->with('success', $branch->is_active
            ? 'Cabang berhasil diaktifkan.'
            : 'Cabang berhasil dinonaktifkan.');
    }

    private function validatedData(Request $request, ?Branch $branch = null): array
    {
        $codeRule = Rule::unique('branches', 'code');

        if ($branch) {
            $codeRule = $codeRule->ignore($branch->getKey());
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', $codeRule],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'string', 'timezone'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['is_active'] = (bool) $validated['is_active'];

        return $validated;
    }
}
