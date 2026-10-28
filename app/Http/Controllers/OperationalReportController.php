<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Refund;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OperationalReportController extends Controller
{
    public function paymentMethods(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
        ], [
            'date_from.date_format' => 'Tanggal awal tidak valid.',
            'date_to.date_format' => 'Tanggal akhir tidak valid.',
            'date_to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ]);
        $from = $validated['date_from'] ?? ($validated['date'] ?? today()->toDateString());
        $to = $validated['date_to'] ?? ($validated['date_from'] ?? ($validated['date'] ?? today()->toDateString()));
        $branch = $this->reportScope($request, $validated['branch_id'] ?? null);

        $start = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
        $query = Transaction::query()->where('status', 'completed')->whereBetween('sold_at', [$start, $end]);
        $this->applyTransactionBranchScope($query, $request->user()->isOwner(), $branch);
        $rows = $query->selectRaw('payment_method, COUNT(*) as transaction_count, COALESCE(SUM(grand_total), 0) as total_amount')
            ->groupBy('payment_method')->orderBy('payment_method')->get();

        return view('reports.payment-methods', [
            'from' => $from,
            'to' => $to,
            'isOwner' => $request->user()->isOwner(),
            'branches' => $request->user()->isOwner() ? Branch::query()->where('is_active', true)->orderBy('name')->get() : collect(),
            'selectedBranch' => $branch,
            'rows' => $rows,
            'transactionCount' => (int) $rows->sum('transaction_count'),
            'totalAmount' => (float) $rows->sum('total_amount'),
        ]);
    }

    public function refunds(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            // Owner may inspect historical refunds belonging to an inactive branch.
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
        ], [
            'date_from.date_format' => 'Tanggal awal tidak valid.',
            'date_to.date_format' => 'Tanggal akhir tidak valid.',
            'date_to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ]);
        $from = $validated['date_from'] ?? ($validated['date'] ?? today()->toDateString());
        $to = $validated['date_to'] ?? ($validated['date_from'] ?? ($validated['date'] ?? today()->toDateString()));
        $start = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
        $branch = $this->refundBranch($request, $validated['branch_id'] ?? null);

        $query = Refund::query()
            ->with(['branch:id,code,name', 'transaction:id,transaction_number,branch_id,status', 'recorder:id,name'])
            ->whereIn('status', ['recorded', 'manually_confirmed'])
            ->whereBetween('recorded_at', [$start, $end])
            ->whereHas('branch')
            ->whereHas('transaction', function ($transaction) {
                $transaction->where('status', 'completed')
                    ->whereColumn('transactions.branch_id', 'refunds.branch_id');
            });

        if ($branch) {
            $query->where('branch_id', $branch->id);
        } elseif (! $request->user()->isOwner()) {
            abort(403);
        }

        $refunds = $query->orderByDesc('recorded_at')->orderByDesc('id')->get();

        return view('reports.refunds', [
            'from' => $from,
            'to' => $to,
            'isOwner' => $request->user()->isOwner(),
            'branches' => $request->user()->isOwner() ? Branch::query()->orderBy('name')->get() : collect(),
            'selectedBranch' => $branch,
            'refunds' => $refunds,
            'totalAmount' => (float) $refunds->sum('amount'),
        ]);
    }

    private function reportScope(Request $request, $branchId): ?Branch
    {
        if ($request->user()->isOwner()) {
            $branch = $branchId ? Branch::query()->whereKey($branchId)->where('is_active', true)->firstOrFail() : null;
            if ($branch) app(\App\Services\BranchContext::class)->set($branch);

            return $branch;
        }

        $active = $request->attributes->get('active_branch');
        abort_unless($active instanceof Branch, 403);
        if ($branchId && (int) $branchId !== (int) $active->id) {
            abort(403);
        }

        return $active;
    }

    private function refundBranch(Request $request, $branchId): ?Branch
    {
        if ($request->user()->isOwner()) {
            return $branchId ? Branch::query()->whereKey($branchId)->firstOrFail() : null;
        }

        $active = $request->attributes->get('active_branch');
        abort_unless($active instanceof Branch, 403);
        if ($branchId && (int) $branchId !== (int) $active->id) {
            abort(403);
        }

        return $active;
    }

    private function applyTransactionBranchScope($query, bool $isOwner, ?Branch $branch): void
    {
        if ($branch) {
            $query->where('branch_id', $branch->id);
        } elseif ($isOwner) {
            $query->whereNotNull('branch_id');
        }
    }
}
