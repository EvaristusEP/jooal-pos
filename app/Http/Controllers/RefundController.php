<?php

namespace App\Http\Controllers;

use App\Models\Refund;
use App\Models\Transaction;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    private $refunds;

    public function __construct(RefundService $refunds)
    {
        $this->refunds = $refunds;
    }

    public function index(Transaction $transaction, Request $request): JsonResponse
    {
        $refunds = $this->refunds->listFor($transaction, $request->user());

        return response()->json(['data' => $refunds]);
    }

    public function store(Transaction $transaction, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refund_type' => ['required', Rule::in(['full', 'partial'])],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'manual_method' => ['required', Rule::in(['cash', 'bank_transfer', 'other'])],
            'items' => ['required_if:refund_type,partial', 'array', 'min:1'],
            'items.*.transaction_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $key = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key')));
        if ($key === '' || strlen($key) > 128) {
            return response()->json(['message' => 'Kunci permintaan refund tidak valid.'], 422);
        }

        if ($validated['refund_type'] === 'full' && ! empty($validated['items'])) {
            return response()->json(['message' => 'Permintaan refund tidak dapat diproses.'], 422);
        }

        $result = $this->refunds->create($transaction, $request->user(), $validated, $key);
        $refund = $result['refund'];

        return response()->json([
            'message' => $result['replayed'] ? 'Catatan refund sebelumnya dikembalikan.' : 'Catatan refund berhasil dibuat; pengembalian uang belum terverifikasi.',
            'data' => $refund,
        ], $result['replayed'] ? 200 : 201);
    }

    public function confirmManual(Transaction $transaction, Refund $refund, Request $request)
    {
        $result = $this->refunds->confirmManual($transaction, $refund, $request->user());
        $confirmed = $result['refund'];
        $message = $result['confirmed_now']
            ? 'Konfirmasi manual dicatat. Sistem tidak memverifikasi perpindahan dana.'
            : 'Refund ini sebelumnya sudah dikonfirmasi manual; tidak ada perubahan tambahan.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => $confirmed]);
        }

        return redirect()->back()->with('status', $message);
    }
}
