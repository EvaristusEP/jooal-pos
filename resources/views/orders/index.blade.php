<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pesanan · JOOAL POS</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900"><x-pos-sidebar />
<main class="min-h-screen min-w-0 p-4 lg:p-6"><div class="mx-auto max-w-7xl space-y-5">
    <header class="pos-page-header bg-white p-5"><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Transaksi · JOOAL POS</p><h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Daftar Pesanan</h1><p class="mt-1 text-sm text-slate-500">Data berasal dari transaksi tersimpan. Refund dicatat terpisah dan tidak mengganti transaksi penjualan.</p></header>
    @if($errors->any())<p class="rounded-lg bg-rose-50 p-3 text-rose-800">{{ $errors->first() }}</p>@endif
    <form method="GET" action="{{ route('orders.index') }}" class="pos-filter orders-filter">
        <label class="text-sm font-semibold md:col-span-2">Cari invoice / pelanggan<input name="q" value="{{ $filters['q'] ?? '' }}" class="pos-input mt-1"></label>
        <label class="text-sm font-semibold">Status tersimpan<select name="status" class="pos-input mt-1"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        @if(auth()->user()->isOwner())<label class="text-sm font-semibold">Cabang<select name="branch_id" class="pos-input mt-1"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)($filters['branch_id'] ?? '')===(string)$branch->id)>{{ $branch->display_name }}</option>@endforeach</select></label>@endif
        <label class="text-sm font-semibold">Dari tanggal<input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="pos-input mt-1"></label>
        <label class="text-sm font-semibold">Sampai tanggal<input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="pos-input mt-1"></label>
        <button class="pos-button bg-emerald-700 font-bold text-white md:col-span-6">Filter pesanan</button>
    </form>
    <section class="min-w-0 overflow-hidden rounded-xl border bg-white p-4"><div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-sm"><thead><tr class="border-b text-xs uppercase text-slate-500"><th class="p-2">Invoice</th><th class="p-2">Waktu</th><th class="p-2">Cabang</th><th class="p-2">Pelanggan</th><th class="p-2">Item transaksi</th><th class="p-2">Pembayaran</th><th class="p-2">Status</th><th class="p-2 text-right">Total</th><th class="p-2"></th></tr></thead><tbody>
        @forelse($orders as $order)<tr class="border-b align-top"><td class="p-2 font-bold">{{ $order->transaction_number }}</td><td class="p-2 whitespace-nowrap">{{ optional($order->sold_at)->format('d/m/Y H:i') }}</td><td class="p-2">{{ optional($order->branch)->display_name ?: 'Histori tanpa cabang' }}</td><td class="p-2">{{ $order->customer_name ?: 'Pelanggan umum' }}</td><td class="p-2"><ul class="space-y-1">@foreach($order->items as $item)<li>{{ $item->menu_name ?: optional($item->menu)->name ?: 'Item transaksi' }} × {{ $item->quantity }}</li>@endforeach</ul></td><td class="p-2">{{ strtoupper($order->payment_method) }}</td><td class="p-2">{{ ucfirst($order->status) }}</td><td class="p-2 whitespace-nowrap text-right font-bold">Rp {{ number_format($order->grand_total,0,',','.') }}</td><td class="p-2"><a href="{{ route('orders.show',$order) }}" class="font-semibold text-emerald-800 underline">Detail</a></td></tr>
        @empty<tr><td colspan="9" class="p-8 text-center text-slate-500">Tidak ada pesanan pada filter ini.</td></tr>@endforelse
    </tbody></table></div><div class="mt-4">{{ $orders->links() }}</div></section>
</div></main></body></html>
