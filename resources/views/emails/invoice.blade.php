<!doctype html>
<html lang="id">
<body style="font-family:Arial,sans-serif;color:#123c35;line-height:1.5">
    <h1>Invoice JOOAL POS</h1>
    <p>Nomor transaksi: <strong>{{ $transaction->transaction_number }}</strong></p>
    <p>Tanggal: {{ optional($transaction->sold_at)->format('d/m/Y H:i') }}</p>
    <p>Cabang: {{ optional($transaction->branch)->display_name ?: 'Tidak tercatat' }}</p>
    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse">
        <thead><tr><th align="left">Item</th><th align="right">Qty</th><th align="right">Jumlah</th></tr></thead>
        <tbody>
        @foreach ($transaction->items as $item)
            <tr><td>{{ $item->menu_name }}</td><td align="right">{{ $item->quantity }}</td><td align="right">Rp {{ number_format($item->line_total, 0, ',', '.') }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @if ((float) $transaction->discount_total > 0)
        <p>Subtotal: Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</p>
        <p>Diskon{{ $transaction->coupon_code ? ' ('.$transaction->coupon_code.')' : '' }}: Rp {{ number_format($transaction->discount_total, 0, ',', '.') }}</p>
    @endif
    <p><strong>Total dibayar: Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</strong></p>
    <p>Metode pembayaran: {{ strtoupper($transaction->payment_method) }}</p>
</body>
</html>
