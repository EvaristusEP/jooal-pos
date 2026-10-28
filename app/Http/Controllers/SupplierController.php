<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::query()->withCount('stockReceipts')->orderBy('name')->get();

        return view('suppliers.index', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = true;
        Supplier::create($data);

        return redirect()->route('suppliers.index')->with('success', 'Pemasok berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return redirect()->route('suppliers.index')->with('success', 'Data pemasok berhasil diperbarui.');
    }

    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return redirect()->route('suppliers.index')->with('success', $supplier->is_active
            ? 'Pemasok berhasil diaktifkan.'
            : 'Pemasok dinonaktifkan dan tidak dapat dipilih untuk penerimaan baru.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'name.required' => 'Nama pemasok wajib diisi.',
            'name.max' => 'Nama pemasok maksimal 150 karakter.',
            'email.email' => 'Format email pemasok tidak valid.',
            'phone.max' => 'Nomor telepon maksimal 50 karakter.',
        ])->validate();
    }
}
