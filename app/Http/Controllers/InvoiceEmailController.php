<?php

namespace App\Http\Controllers;

use App\Mail\InvoiceEmail;
use App\Models\Branch;
use App\Models\Transaction;
use App\Services\MailDeliveryStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InvoiceEmailController extends Controller
{
    public function send(Request $request, Transaction $transaction, MailDeliveryStatus $mailStatus): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $query = Transaction::query()->whereKey($transaction->getKey());
        if (! $request->user()->isOwner()) {
            $branch = $request->attributes->get('active_branch');
            abort_unless($branch instanceof Branch, 403);
            $query->where('branch_id', $branch->getKey());
        }
        $transaction = $query->with(['branch', 'cashier', 'items'])->firstOrFail();

        if ($transaction->status !== 'completed') {
            return back()->withErrors(['invoice_email' => 'Invoice hanya dapat dikirim untuk transaksi selesai.']);
        }

        $status = $mailStatus->current();
        if (! $status['configured']) {
            return back()->withErrors(['invoice_email' => $status['message']]);
        }

        try {
            Mail::to($validated['email'])->send(new InvoiceEmail($transaction));
        } catch (Throwable $exception) {
            Log::error('Invoice email transport failed.', ['transaction_id' => $transaction->getKey(), 'exception' => $exception]);

            return back()->withErrors(['invoice_email' => 'Email tidak berhasil dikirim oleh transport. Periksa konfigurasi dan log aplikasi.']);
        }

        return back()->with('success', 'Invoice berhasil diserahkan ke transport email untuk pengiriman.');
    }
}
