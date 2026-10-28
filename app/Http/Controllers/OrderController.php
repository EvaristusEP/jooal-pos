<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MailDeliveryStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
        ]);
        if ($request->user()->isOwner() && ! empty($filters['branch_id'])) {
            $selectedBranch = Branch::query()->findOrFail($filters['branch_id']);
            if ($selectedBranch->is_active) app(\App\Services\BranchContext::class)->set($selectedBranch);
        }

        $query = Transaction::query()->with(['branch:id,code,name', 'cashier:id,name'])
            ->withCount('items')->orderByDesc('sold_at')->orderByDesc('id');
        $this->scopeToUser($query, $request, $filters['branch_id'] ?? null);

        if (! empty($filters['q'])) {
            $term = trim($filters['q']);
            $query->where(function ($orders) use ($term) {
                $orders->where('transaction_number', 'like', '%'.$term.'%')
                    ->orWhere('customer_name', 'like', '%'.$term.'%');
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('sold_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('sold_at', '<=', $filters['date_to']);
        }

        $statusesQuery = Transaction::query()->select('status')->distinct();
        $this->scopeToUser($statusesQuery, $request, $filters['branch_id'] ?? null);
        $statuses = $statusesQuery->orderBy('status')->pluck('status');

        return view('orders.index', [
            'orders' => $query->paginate(20)->withQueryString(),
            'statuses' => $statuses,
            'filters' => $filters,
            'branches' => $request->user()->isOwner() ? Branch::query()->orderBy('name')->get() : collect(),
        ]);
    }

    public function show(Request $request, Transaction $transaction, MailDeliveryStatus $mailStatus): View
    {
        $query = Transaction::query()->whereKey($transaction->getKey());
        $this->scopeToUser($query, $request);
        $transaction = $query->with(['branch', 'cashier', 'items', 'refunds.items.transactionItem', 'refunds.recorder', 'refunds.confirmer'])->firstOrFail();
        $canViewRefunds = $request->user()->hasAnyRole([User::ROLE_OWNER, User::ROLE_MANAGER]);
        $refundSummary = $canViewRefunds ? app(\App\Services\RefundService::class)->summaryFor($transaction, $request->user()) : null;

        return view('orders.show', [
            'transaction' => $transaction,
            'canViewRefunds' => $canViewRefunds,
            'refundSummary' => $refundSummary,
            'mailStatus' => $mailStatus->current(),
        ]);
    }

    private function scopeToUser($query, Request $request, ?int $branchId = null): void
    {
        if ($request->user()->isOwner()) {
            if ($branchId) $query->where('branch_id', $branchId);
            return;
        }

        $branch = $request->attributes->get('active_branch');
        abort_unless($branch instanceof Branch, 403);
        if ($branchId && (int) $branchId !== (int) $branch->getKey()) abort(403);
        $query->where('branch_id', $branch->getKey());
    }
}
