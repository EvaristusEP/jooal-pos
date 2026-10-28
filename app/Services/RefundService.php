<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RefundService
{
    private const GENERIC_CONFLICT = 'Permintaan refund tidak dapat diproses.';

    public function listFor(Transaction $transaction, User $user): Collection
    {
        $this->authorizeTransaction($transaction, $user);

        return Refund::query()
            ->with(['items.transactionItem', 'recorder', 'confirmer'])
            ->where('transaction_id', $transaction->getKey())
            ->orderBy('created_at')
            ->get();
    }

    public function summaryFor(Transaction $transaction, User $user): array
    {
        abort_unless($user->hasAnyRole([User::ROLE_OWNER, User::ROLE_MANAGER]), 403);
        if ($transaction->branch_id) {
            $this->authorizeTransaction($transaction, $user);
        }

        $refunds = $transaction->relationLoaded('refunds')
            ? $transaction->refunds
            : $transaction->refunds()->with('items.transactionItem')->get();
        $summary = [
            'has_refunds' => $refunds->isNotEmpty(),
            'total_refunded' => null,
            'remaining_refundable' => null,
            'remaining_quantities' => null,
        ];

        $grandTotalCents = $this->safeCents($transaction->grand_total);
        if ($grandTotalCents === null || $grandTotalCents < 0) {
            return $summary;
        }

        $headerTotalCents = 0;
        $detailTotalCents = 0;
        $refundedQuantities = [];
        $refundedAmounts = [];
        $originalItems = $transaction->relationLoaded('items')
            ? $transaction->items->keyBy('id')
            : $transaction->items()->get()->keyBy('id');
        $refundableLineCents = $this->refundableLineCents($transaction, $originalItems);
        if ($refundableLineCents === null) {
            return $summary;
        }
        $originalTotalCents = 0;
        foreach ($originalItems as $item) {
            $lineCents = $refundableLineCents[$item->getKey()] ?? null;
            if ($lineCents === null || (int) $item->quantity < 1) {
                return $summary;
            }
            $originalTotalCents += $lineCents;
        }

        foreach ($refunds as $refund) {
            $refundCents = $this->safeCents($refund->amount);
            if ($refundCents === null || $refundCents < 0) {
                return $summary;
            }
            $headerTotalCents += $refundCents;
            $refundDetailCents = 0;
            $refundsItems = $refund->relationLoaded('items') ? $refund->items : $refund->items()->with('transactionItem')->get();
            foreach ($refundsItems as $refundItem) {
                $transactionItemId = (int) $refundItem->transaction_item_id;
                $originalItem = $originalItems->get($transactionItemId);
                $amountCents = $this->safeCents($refundItem->amount);
                $quantity = (int) $refundItem->quantity;
                if (! $originalItem || $amountCents === null || $amountCents < 0 || $quantity < 1) {
                    return $summary;
                }

                $lineCents = $refundableLineCents[$originalItem->getKey()] ?? null;
                $soldQuantity = (int) $originalItem->quantity;
                $priorQuantity = $refundedQuantities[$transactionItemId] ?? 0;
                $priorAmount = $refundedAmounts[$transactionItemId] ?? 0;
                $newQuantity = $priorQuantity + $quantity;
                if ($newQuantity > $soldQuantity || $lineCents === null
                    || $this->safeAllocation($lineCents, $newQuantity, $soldQuantity) === null
                    || $this->safeAllocation($lineCents, $priorQuantity, $soldQuantity) !== $priorAmount) {
                    return $summary;
                }

                $expectedAmount = $this->safeAllocation($lineCents, $newQuantity, $soldQuantity) - $priorAmount;
                if ($amountCents !== $expectedAmount) {
                    return $summary;
                }
                $refundedQuantities[$transactionItemId] = $newQuantity;
                $refundedAmounts[$transactionItemId] = $priorAmount + $amountCents;
                $refundDetailCents += $amountCents;
                $detailTotalCents += $amountCents;
            }
            if ($refundDetailCents !== $refundCents) {
                return $summary;
            }
        }

        $summary['total_refunded'] = $this->fromCents($headerTotalCents);
        if ($transaction->status === 'completed'
            && $transaction->branch_id
            && $originalTotalCents === $grandTotalCents
            && $detailTotalCents === $headerTotalCents
            && $headerTotalCents <= $grandTotalCents) {
            $summary['remaining_refundable'] = $this->fromCents($grandTotalCents - $headerTotalCents);
            $summary['remaining_quantities'] = [];
            foreach ($originalItems as $item) {
                $summary['remaining_quantities'][$item->getKey()] = max(0, (int) $item->quantity - ($refundedQuantities[$item->getKey()] ?? 0));
            }
        }

        return $summary;
    }

    public function create(Transaction $transaction, User $user, array $data, string $idempotencyKey): array
    {
        $payload = [
            'transaction_id' => (int) $transaction->getKey(),
            'branch_id' => (int) $transaction->branch_id,
            'user_id' => (int) $user->getKey(),
            'refund_type' => $data['refund_type'],
            'reason' => trim($data['reason']),
            'manual_method' => $data['manual_method'],
            'items' => $data['refund_type'] === 'partial' ? $this->canonicalItems($data['items']) : [],
        ];
        $fingerprint = hash('sha256', json_encode($payload));

        try {
            $refund = DB::transaction(function () use ($transaction, $user, $data, $idempotencyKey, $fingerprint, $payload) {
                $lockedTransaction = Transaction::query()->whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
                $branch = $this->authorizeTransaction($lockedTransaction, $user);
                if ((int) $branch->getKey() !== (int) $payload['branch_id']) {
                    abort(409, self::GENERIC_CONFLICT);
                }

                $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    $this->assertSameRequest($existing, $lockedTransaction, $branch, $user, $fingerprint);

                    return [$existing, true];
                }

                if ($lockedTransaction->status !== 'completed') {
                    abort(409, self::GENERIC_CONFLICT);
                }

                $items = TransactionItem::query()
                    ->where('transaction_id', $lockedTransaction->getKey())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($items->isEmpty()) {
                    abort(409, self::GENERIC_CONFLICT);
                }

                $refundableLineCents = $this->refundableLineCents($lockedTransaction, $items->keyBy('id'));
                if ($refundableLineCents === null) {
                    abort(409, self::GENERIC_CONFLICT);
                }

                $grandTotalCents = $this->toCents($lockedTransaction->grand_total);
                $originalItemCents = 0;
                foreach ($items as $item) {
                    $originalItemCents += $refundableLineCents[$item->getKey()];
                }
                if ($grandTotalCents <= 0 || $originalItemCents !== $grandTotalCents) {
                    Log::warning('Refund blocked because transaction and item snapshot totals do not reconcile.', [
                        'transaction_id' => $lockedTransaction->getKey(),
                        'transaction_total_cents' => $grandTotalCents,
                        'item_snapshot_total_cents' => $originalItemCents,
                    ]);
                    abort(409, self::GENERIC_CONFLICT);
                }

                $history = DB::table('refund_items')
                    ->join('refunds', 'refunds.id', '=', 'refund_items.refund_id')
                    ->where('refunds.transaction_id', $lockedTransaction->getKey())
                    ->select('refund_items.transaction_item_id')
                    ->selectRaw('SUM(refund_items.quantity) as refunded_quantity')
                    ->selectRaw('SUM(refund_items.amount) as refunded_amount')
                    ->groupBy('refund_items.transaction_item_id')
                    ->get()
                    ->keyBy('transaction_item_id');
                $priorRefundCents = $this->sumRefundCents($lockedTransaction);
                $priorItemRefundCents = 0;
                foreach ($items as $item) {
                    $prior = $history->get($item->getKey());
                    $priorQuantity = (int) optional($prior)->refunded_quantity;
                    $priorCents = $this->toCents(optional($prior)->refunded_amount ?: '0.00');
                    $lineCents = $refundableLineCents[$item->getKey()];
                    if ($priorQuantity > (int) $item->quantity
                        || $priorCents !== $this->allocatedCents($lineCents, $priorQuantity, (int) $item->quantity)) {
                        Log::warning('Refund blocked because prior refund item totals do not reconcile.', [
                            'transaction_id' => $lockedTransaction->getKey(),
                            'transaction_item_id' => $item->getKey(),
                        ]);
                        abort(409, self::GENERIC_CONFLICT);
                    }
                    $priorItemRefundCents += $priorCents;
                }
                if ($priorItemRefundCents !== $priorRefundCents) {
                    Log::warning('Refund blocked because refund header and item totals do not reconcile.', [
                        'transaction_id' => $lockedTransaction->getKey(),
                        'refund_header_total_cents' => $priorRefundCents,
                        'refund_item_total_cents' => $priorItemRefundCents,
                    ]);
                    abort(409, self::GENERIC_CONFLICT);
                }
                $remainingTransactionCents = $grandTotalCents - $priorRefundCents;
                if ($remainingTransactionCents <= 0) {
                    abort(409, self::GENERIC_CONFLICT);
                }

                $selected = [];
                if ($data['refund_type'] === 'full') {
                    foreach ($items as $item) {
                        $prior = $history->get($item->getKey());
                        $priorQuantity = (int) optional($prior)->refunded_quantity;
                        $quantityLeft = (int) $item->quantity - $priorQuantity;
                        if ($quantityLeft > 0) {
                            $selected[] = ['item' => $item, 'quantity' => $quantityLeft, 'prior' => $prior];
                        }
                    }
                } else {
                    $itemById = $items->keyBy('id');
                    foreach ($payload['items'] as $requested) {
                        $item = $itemById->get($requested['transaction_item_id']);
                        if (! $item) {
                            abort(422, self::GENERIC_CONFLICT);
                        }
                        $selected[] = [
                            'item' => $item,
                            'quantity' => $requested['quantity'],
                            'prior' => $history->get($item->getKey()),
                        ];
                    }
                }

                if (empty($selected)) {
                    abort(409, self::GENERIC_CONFLICT);
                }

                $refundLines = [];
                $refundCents = 0;
                foreach ($selected as $selection) {
                    /** @var TransactionItem $item */
                    $item = $selection['item'];
                    $quantity = (int) $selection['quantity'];
                    $soldQuantity = (int) $item->quantity;
                    $priorQuantity = (int) optional($selection['prior'])->refunded_quantity;
                    $priorCents = $this->toCents(optional($selection['prior'])->refunded_amount ?: '0.00');
                    $lineCents = $refundableLineCents[$item->getKey()];

                    if ($quantity < 1 || $priorQuantity + $quantity > $soldQuantity) {
                        abort(422, self::GENERIC_CONFLICT);
                    }
                    if ($priorCents !== $this->allocatedCents($lineCents, $priorQuantity, $soldQuantity)) {
                        abort(409, self::GENERIC_CONFLICT);
                    }

                    $amountCents = $this->allocatedCents($lineCents, $priorQuantity + $quantity, $soldQuantity) - $priorCents;
                    $refundLines[] = ['item' => $item, 'quantity' => $quantity, 'amount_cents' => $amountCents];
                    $refundCents += $amountCents;
                }

                if ($refundCents < 1 || $refundCents > $remainingTransactionCents) {
                    abort(409, self::GENERIC_CONFLICT);
                }

                $postRefundTotalCents = $priorRefundCents + $refundCents;
                $refund = Refund::create([
                    'refund_number' => 'RF-'.Str::upper((string) Str::uuid()),
                    'transaction_id' => $lockedTransaction->getKey(),
                    'branch_id' => $branch->getKey(),
                    'recorded_by' => $user->getKey(),
                    'refund_type' => $postRefundTotalCents === $grandTotalCents ? 'full' : 'partial',
                    'amount' => $this->fromCents($refundCents),
                    'status' => 'recorded',
                    'manual_method' => $data['manual_method'],
                    'reason' => trim($data['reason']),
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $fingerprint,
                    'recorded_at' => now(),
                ]);

                foreach ($refundLines as $line) {
                    RefundItem::create([
                        'refund_id' => $refund->getKey(),
                        'transaction_item_id' => $line['item']->getKey(),
                        'quantity' => $line['quantity'],
                        'amount' => $this->fromCents($line['amount_cents']),
                    ]);
                }

                return [$refund->load('items.transactionItem'), false];
            }, 3);

            return ['refund' => $refund[0], 'replayed' => $refund[1]];
        } catch (QueryException $exception) {
            $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                $branch = Branch::query()->find($transaction->branch_id);
                if ($branch) {
                    $this->assertSameRequest($existing, $transaction, $branch, $user, $fingerprint);

                    return ['refund' => $existing->load('items.transactionItem'), 'replayed' => true];
                }
                abort(409, self::GENERIC_CONFLICT);
            }

            throw $exception;
        }
    }

    public function confirmManual(Transaction $transaction, Refund $refund, User $user): array
    {
        return DB::transaction(function () use ($transaction, $refund, $user) {
            $lockedTransaction = Transaction::query()->whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizeTransaction($lockedTransaction, $user);

            $lockedRefund = Refund::query()
                ->whereKey($refund->getKey())
                ->where('transaction_id', $lockedTransaction->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $lockedRefund->branch_id !== (int) $lockedTransaction->branch_id) {
                abort(409, self::GENERIC_CONFLICT);
            }

            $confirmedNow = $lockedRefund->status === 'recorded';
            if ($confirmedNow) {
                $lockedRefund->status = 'manually_confirmed';
                $lockedRefund->confirmed_by = $user->getKey();
                $lockedRefund->manually_confirmed_at = now();
                $lockedRefund->save();
            }

            return [
                'refund' => $lockedRefund->load(['items.transactionItem', 'recorder', 'confirmer']),
                'confirmed_now' => $confirmedNow,
            ];
        }, 3);
    }

    private function authorizeTransaction(Transaction $transaction, User $user): Branch
    {
        abort_unless($user->hasAnyRole([User::ROLE_OWNER, User::ROLE_MANAGER]), 403);
        if (! $transaction->branch_id) {
            abort(409, self::GENERIC_CONFLICT);
        }

        $branch = Branch::query()->find($transaction->branch_id);
        if (! $branch) {
            abort(409, self::GENERIC_CONFLICT);
        }

        if ($user->isManager()) {
            $assigned = $branch->is_active && $user->branches()
                ->whereKey($branch->getKey())
                ->wherePivot('is_active', true)
                ->exists();
            abort_unless($assigned, 403);
        }

        return $branch;
    }

    private function assertSameRequest(Refund $refund, Transaction $transaction, Branch $branch, User $user, string $fingerprint): void
    {
        $matches = (int) $refund->transaction_id === (int) $transaction->getKey()
            && (int) $refund->branch_id === (int) $branch->getKey()
            && (int) $refund->recorded_by === (int) $user->getKey()
            && hash_equals((string) $refund->request_fingerprint, $fingerprint);

        abort_unless($matches, 409, self::GENERIC_CONFLICT);
    }

    private function canonicalItems(array $items): array
    {
        $canonical = [];
        foreach ($items as $item) {
            $canonical[] = [
                'transaction_item_id' => (int) $item['transaction_item_id'],
                'quantity' => (int) $item['quantity'],
            ];
        }
        usort($canonical, function (array $left, array $right) {
            return $left['transaction_item_id'] <=> $right['transaction_item_id'];
        });

        return $canonical;
    }

    private function sumRefundCents(Transaction $transaction): int
    {
        $amounts = Refund::query()->where('transaction_id', $transaction->getKey())->pluck('amount');
        return $amounts->reduce(function (int $sum, $amount) {
            return $sum + $this->toCents($amount);
        }, 0);
    }

    private function toCents($value): int
    {
        $value = (string) $value;
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            abort(409, self::GENERIC_CONFLICT);
        }
        $whole = (int) $matches[1];
        if ($whole > intdiv(PHP_INT_MAX, 100)) {
            abort(409, self::GENERIC_CONFLICT);
        }
        $fraction = isset($matches[2]) ? (int) str_pad($matches[2], 2, '0') : 0;

        return $whole * 100 + $fraction;
    }

    private function safeCents($value): ?int
    {
        $value = (string) $value;
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches)) {
            return null;
        }
        $whole = (int) $matches[1];
        if ($whole > intdiv(PHP_INT_MAX, 100)) {
            return null;
        }

        return $whole * 100 + (isset($matches[2]) ? (int) str_pad($matches[2], 2, '0') : 0);
    }

    private function safeAllocation(int $lineCents, int $quantity, int $soldQuantity): ?int
    {
        if ($soldQuantity < 1 || $quantity < 0 || $quantity > $soldQuantity
            || ($quantity > 0 && $lineCents > intdiv(PHP_INT_MAX, $quantity))) {
            return null;
        }

        return intdiv($lineCents * $quantity, $soldQuantity);
    }

    /**
     * Allocate a transaction-level coupon discount across item snapshots so
     * partial refunds never refund more than the discounted amount paid.
     */
    private function refundableLineCents(Transaction $transaction, Collection $items): ?array
    {
        // Keep allocation stable whether callers loaded the relation ascending,
        // descending, or through a different eager-load query.
        $items = $items->sortBy(function ($item) {
            return (int) $item->getKey();
        })->values();

        $grossLines = [];
        $grossTotal = 0;
        foreach ($items as $item) {
            $lineCents = $this->safeCents($item->line_total);
            if ($lineCents === null || $lineCents < 0 || (int) $item->quantity < 1) {
                return null;
            }
            $grossLines[$item->getKey()] = $lineCents;
            $grossTotal += $lineCents;
        }

        $discountCents = $this->safeCents($transaction->discount_total);
        if ($discountCents === null || $discountCents < 0 || $discountCents > $grossTotal) {
            return null;
        }
        if ($discountCents === 0) {
            return $grossLines;
        }

        $discountShares = [];
        $allocatedDiscount = 0;
        foreach ($grossLines as $key => $lineCents) {
            $share = round($lineCents * ($discountCents / max(1, $grossTotal)), 8);
            $lineDiscount = (int) floor($share);
            if ($lineDiscount < 0 || $lineDiscount > $lineCents) {
                return null;
            }
            $allocatedDiscount += $lineDiscount;
            $discountShares[$key] = [
                'discount' => $lineDiscount,
                'remainder' => $share - $lineDiscount,
                'gross' => $lineCents,
            ];
        }

        $remainingCents = $discountCents - $allocatedDiscount;
        if ($remainingCents < 0 || $remainingCents > count($discountShares)) {
            return null;
        }
        uksort($discountShares, function ($leftKey, $rightKey) use ($discountShares) {
            $fractionOrder = $discountShares[$rightKey]['remainder'] <=> $discountShares[$leftKey]['remainder'];

            return $fractionOrder !== 0 ? $fractionOrder : ((int) $leftKey <=> (int) $rightKey);
        });
        foreach ($discountShares as $key => $share) {
            if ($remainingCents === 0) {
                break;
            }
            if ($share['discount'] < $share['gross']) {
                $discountShares[$key]['discount']++;
                $remainingCents--;
            }
        }
        if ($remainingCents !== 0) {
            return null;
        }

        $netLines = [];
        foreach ($discountShares as $key => $share) {
            $netLines[$key] = $share['gross'] - $share['discount'];
        }

        return array_sum($netLines) === $grossTotal - $discountCents ? $netLines : null;
    }

    private function allocatedCents(int $lineCents, int $cumulativeQuantity, int $soldQuantity): int
    {
        if ($soldQuantity < 1 || $cumulativeQuantity < 0 || $cumulativeQuantity > $soldQuantity
            || ($cumulativeQuantity > 0 && $lineCents > intdiv(PHP_INT_MAX, $cumulativeQuantity))) {
            abort(409, self::GENERIC_CONFLICT);
        }

        return intdiv($lineCents * $cumulativeQuantity, $soldQuantity);
    }

    private function fromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
