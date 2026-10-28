<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActiveBranchController extends Controller
{
    public function select(Request $request, BranchContext $branchContext): View
    {
        $branches = $branchContext->availableFor($request->user());
        $activeBranch = $branchContext->activeFor($request->user());

        return view('branches.select', compact('branches', 'activeBranch'));
    }

    public function update(Request $request, BranchContext $branchContext): RedirectResponse
    {
        $destinations = [
            'dashboard' => 'dashboard', 'pos' => 'dashboard.pos', 'attendance' => 'attendance.index',
            'tables' => 'tables.index', 'customers' => 'customers.index', 'inventory' => 'dashboard.inventory',
            'inventory-stock-in' => 'inventory.stock-in.index', 'inventory-stocks' => 'inventory.stocks.index',
            'inventory-movements' => 'inventory.movements.index', 'inventory-materials' => 'inventory.materials.index',
            'master-menu' => 'master-menu.index', 'orders' => 'orders.index', 'history' => 'history',
            'reports-sales' => 'reports.sales', 'reports-payment' => 'reports.payment-methods',
            'reports-refunds' => 'reports.refunds', 'reports-attendance' => 'reports.attendance',
        ];
        $validated = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('is_active', true),
            ],
            'return_to' => ['nullable', Rule::in(array_keys($destinations))],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);

        if (! $branchContext->canAccess($request->user(), $branch)) {
            abort(403);
        }

        if ((int) session(BranchContext::SESSION_KEY) !== $branch->getKey() && session()->has('pos_cart') && ! empty(session('pos_cart'))) {
            return back()->withErrors(['branch' => 'Kosongkan cart sebelum berpindah cabang.']);
        }

        $branchContext->set($branch);

        $destination = $validated['return_to'] ?? 'dashboard';
        $target = route($destinations[$destination]);
        if (in_array($destination, ['tables', 'customers', 'inventory', 'inventory-stock-in', 'inventory-stocks', 'inventory-movements', 'orders', 'history', 'reports-sales', 'reports-payment', 'reports-refunds', 'reports-attendance'], true)) {
            $target .= '?branch_id='.$branch->id;
        }
        return redirect()->to($target)->with('success', 'Cabang aktif diubah ke '.$branch->name.'.');
    }
}
