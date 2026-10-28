<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request, BranchContext $context): View
    {
        $active = $context->activeFor($request->user());
        if (! $request->user()->isOwner()) abort_unless($active && $context->canAccess($request->user(), $active), 403);
        $scopeAll = $request->user()->isOwner() && $request->query('branch_id') === 'all';
        $selected = null;
        if ($request->user()->isOwner() && ! $scopeAll) {
            $selectedId = $request->query('branch_id', optional($active)->id);
            if ($selectedId) {
                $selected = Branch::query()->findOrFail($selectedId);
                abort_unless($selected->is_active, 403);
                if ($request->query('branch_id') !== null) $context->set($selected);
            }
        }
        $customers = Customer::query()->with('branch')
            ->when(! $request->user()->isOwner(), fn ($q) => $q->where('branch_id', $active->id))
            ->when($request->user()->isOwner() && ! $scopeAll && $selected, fn ($q) => $q->where('branch_id', $selected->id))
            ->orderBy('name')->get();
        $branches = $request->user()->isOwner() ? Branch::query()->orderBy('name')->get() : collect([$active]);
        return view('customers.index', compact('customers', 'branches', 'active', 'scopeAll', 'selected'));
    }

    public function store(Request $request, BranchContext $context)
    {
        $data = $this->validated($request);
        $branchId = $this->customerBranchId($request, $context, $data['branch_id'] ?? null);
        unset($data['branch_id']);
        Customer::create($data + ['branch_id' => $branchId, 'is_active' => true]);
        return redirect()->route('customers.index')->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer, BranchContext $context)
    {
        $this->authorizeCustomer($request, $context, $customer);
        $data = $this->validated($request, $customer);
        unset($data['branch_id']);
        $customer->update($data);
        return redirect()->route('customers.index')->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function edit(Request $request, Customer $customer, BranchContext $context): View
    {
        $this->authorizeCustomer($request, $context, $customer);
        return view('customers.edit', compact('customer'));
    }

    public function show(Request $request, Customer $customer, BranchContext $context): View
    {
        $this->authorizeCustomer($request, $context, $customer);
        $branchId = $customer->branch_id;
        $customer->load(['branch', 'transactions' => function ($q) use ($request, $branchId) {
            if (! $request->user()->isOwner()) $q->where('branch_id', $branchId);
            $q->latest('sold_at')->limit(20);
        }]);
        return view('customers.show', compact('customer'));
    }

    public function toggleStatus(Request $request, Customer $customer, BranchContext $context)
    {
        $this->authorizeCustomer($request, $context, $customer);
        $customer->update(['is_active' => ! $customer->is_active]);
        return redirect()->route('customers.index')->with('success', 'Status pelanggan diperbarui.');
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+() .-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nama pelanggan wajib diisi.', 'phone.regex' => 'Format nomor telepon tidak valid.',
            'email.email' => 'Format email tidak valid.',
        ]);
    }

    private function customerBranchId(Request $request, BranchContext $context, ?int $branchId): ?int
    {
        if ($request->user()->isOwner()) {
            if ($branchId) abort_unless($context->canAccess($request->user(), Branch::findOrFail($branchId)), 403);
            return $branchId;
        }
        $active = $context->activeFor($request->user());
        abort_unless($active && $context->canAccess($request->user(), $active), 403);
        if ($branchId !== null) abort_unless((int) $branchId === (int) $active->id, 403);
        return $active->id;
    }

    private function authorizeCustomer(Request $request, BranchContext $context, Customer $customer): void
    {
        if ($request->user()->isOwner()) return;
        $active = $context->activeFor($request->user());
        abort_unless($active && $customer->branch_id && (int) $customer->branch_id === (int) $active->id && $context->canAccess($request->user(), $active), 404);
    }
}
