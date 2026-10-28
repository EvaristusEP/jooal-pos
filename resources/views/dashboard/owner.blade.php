<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard Owner · JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased">
    <x-pos-sidebar />
    <main class="min-h-screen p-4 transition-[margin] duration-300 lg:ml-20 lg:p-6">
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="pos-page-header rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-700">JOOAL POS · Owner</p>
                <h1 class="mt-1 text-3xl font-extrabold text-[#123c35]">Dashboard Manajemen</h1>
                <p class="mt-2 text-sm text-slate-500">Ringkasan seluruh cabang. Pilih cabang aktif di sidebar untuk melihat konteks operasional tertentu.</p>
            </header>

            @if (session('success'))
                <div class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
            @endif

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-3xl bg-[#123c35] p-5 text-white shadow-lg">
                    <p class="text-sm font-semibold text-emerald-200">Cabang aktif</p>
                    <p class="mt-2 text-4xl font-extrabold">{{ $activeBranchCount }}</p>
                </article>
                <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <p class="text-sm font-semibold text-slate-500">Total transaksi selesai</p>
                    <p class="mt-2 text-4xl font-extrabold text-slate-950">{{ number_format($totalTransactions, 0, ',', '.') }}</p>
                </article>
                <article class="rounded-3xl bg-amber-50 p-5 ring-1 ring-amber-100">
                    <p class="text-sm font-semibold text-amber-700">Stok rendah</p>
                    <p class="mt-2 text-4xl font-extrabold text-amber-900">{{ $lowStockCount }}</p>
                    <a href="{{ route('inventory.stocks.index') }}" class="mt-2 inline-block text-xs font-bold text-amber-800">Buka inventaris →</a>
                </article>
                <article class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <p class="text-sm font-semibold text-slate-500">Total penjualan</p>
                    <p class="mt-2 text-3xl font-extrabold text-emerald-800">Rp {{ number_format($totalSales, 0, ',', '.') }}</p>
                </article>
            </section>

            <section class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Performa</p>
                        <h2 class="mt-1 text-xl font-extrabold">Ringkasan per cabang</h2>
                    </div>
                    <a href="{{ route('reports.sales') }}" class="pos-button bg-emerald-700 text-white hover:bg-emerald-800">Buka laporan</a>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[620px] text-left text-sm">
                        <thead class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-400">
                            <tr><th class="px-3 py-3">Cabang</th><th class="px-3 py-3">Status</th><th class="px-3 py-3 text-right">Transaksi</th><th class="px-3 py-3 text-right">Penjualan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($branchSummaries as $summary)
                                <tr>
                                    <td class="px-3 py-4"><strong class="text-slate-950">{{ $summary['branch']->display_name }}</strong></td>
                                    <td class="px-3 py-4"><span class="rounded-lg px-2 py-1 text-xs font-bold {{ $summary['branch']->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $summary['branch']->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td class="px-3 py-4 text-right font-semibold">{{ number_format($summary['total_transactions'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-4 text-right font-bold text-emerald-800">Rp {{ number_format($summary['total_sales'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">Belum ada cabang.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($unmappedTransactions > 0)
                <div class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ number_format($unmappedTransactions, 0, ',', '.') }} transaksi dengan total Rp {{ number_format($unmappedSales, 0, ',', '.') }} belum memiliki pemetaan cabang dan tidak dimasukkan ke ringkasan per cabang.</div>
            @endif
        </div>
    </main>
</body>
</html>
