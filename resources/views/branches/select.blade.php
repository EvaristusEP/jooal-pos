<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pilih Cabang · JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-emerald-50 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
        <section class="w-full rounded-3xl bg-white p-7 shadow-xl ring-1 ring-emerald-100">
            <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-700">JOOAL POS</p>
            <h1 class="mt-2 text-3xl font-extrabold text-[#123c35]">Pilih cabang aktif</h1>
            <p class="mt-2 text-sm text-slate-500">Semua transaksi baru akan dicatat pada cabang yang dipilih.</p>

            @if ($errors->any())
                <div class="mt-5 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif

            <div class="mt-6 space-y-3">
                @forelse ($branches as $branch)
                    <form method="POST" action="{{ route('branches.select.update') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                        <button type="submit" class="flex w-full items-center justify-between rounded-2xl border p-4 text-left transition {{ optional($activeBranch)->id === $branch->id ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/50' }}">
                            <span><strong class="block text-slate-950">{{ $branch->display_name }}</strong></span>
                            <span class="text-sm font-bold text-emerald-700">{{ optional($activeBranch)->id === $branch->id ? 'Aktif' : 'Pilih' }}</span>
                        </button>
                    </form>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Belum ada cabang yang dapat digunakan. Hubungi owner.</div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <button type="submit" class="text-sm font-bold text-slate-500 hover:text-slate-700">Keluar</button>
            </form>
        </section>
    </main>
</body>
</html>
