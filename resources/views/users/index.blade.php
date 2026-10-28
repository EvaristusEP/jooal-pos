<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Manajemen Pengguna · JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased">
    <x-pos-sidebar />
    <main class="min-h-screen p-4 transition-[margin] duration-300 lg:ml-20 lg:p-6">
        <div class="mx-auto max-w-6xl space-y-6">
            <header class="pos-page-header rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
                <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-700">Owner</p>
                <h1 class="mt-1 text-3xl font-extrabold text-[#123c35]">Manajemen Pengguna</h1>
                <p class="mt-2 text-sm text-slate-500">Kelola akun, role, status, dan penugasan cabang tanpa menampilkan password.</p>
            </header>

            @if (session('success'))<div class="rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
            @if ($errors->any())<div class="rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>@endif

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <section class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-100">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Daftar</p><h2 class="mt-1 text-xl font-extrabold">Akun pengguna</h2></div><span class="rounded-xl bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800">{{ $users->count() }} akun</span></div>
                    <div class="mt-5 space-y-3">
                        @forelse ($users as $user)
                            <article class="rounded-2xl border border-slate-100 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div><div class="flex flex-wrap items-center gap-2"><h3 class="font-extrabold text-slate-950">{{ $user->name }}</h3><span class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ $user->role }}</span><span class="rounded-lg px-2 py-1 text-xs font-bold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span></div><p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p><p class="mt-1 text-xs text-slate-400">{{ $user->branches_count }} cabang ditugaskan</p></div>
                                    <div class="flex shrink-0 gap-2"><a href="{{ route('users.edit', $user) }}" class="pos-button bg-white px-3 py-2 text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">Edit</a><form method="POST" action="{{ route('users.toggle-status', $user) }}">@csrf @method('PATCH')<button type="submit" class="pos-button px-3 py-2 {{ $user->is_active ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-700 text-white hover:bg-emerald-800' }}">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></div>
                                </div>
                            </article>
                        @empty<div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Belum ada pengguna.</div>@endforelse
                    </div>
                </section>

                <section class="h-fit rounded-3xl bg-[#123c35] p-5 text-white shadow-lg">
                    <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-200">Tambah</p><h2 class="mt-1 text-xl font-extrabold">Akun baru</h2>
                    <form method="POST" action="{{ route('users.store') }}" class="mt-5 space-y-4">@csrf
                        <label class="block text-sm font-bold text-emerald-50">Nama<input name="name" value="{{ old('name') }}" required maxlength="255" class="pos-input mt-2"></label>
                        <label class="block text-sm font-bold text-emerald-50">Email<input type="email" name="email" value="{{ old('email') }}" required maxlength="255" class="pos-input mt-2"></label>
                        <label class="block text-sm font-bold text-emerald-50">Role<select name="role" required class="pos-input mt-2">@foreach (\App\Models\User::ROLES as $role)<option value="{{ $role }}" @selected(old('role', 'cashier') === $role)>{{ $role }}</option>@endforeach</select></label>
                        <label class="block text-sm font-bold text-emerald-50">Password<input type="password" name="password" required minlength="8" autocomplete="new-password" class="pos-input mt-2"></label>
                        <label class="block text-sm font-bold text-emerald-50">Konfirmasi password<input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" class="pos-input mt-2"></label>
                        <label class="flex items-center gap-3 text-sm font-bold text-emerald-50"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300 text-emerald-700"> Akun aktif</label>
                        <button type="submit" class="pos-button w-full bg-emerald-400 text-[#123c35] hover:bg-emerald-300">Buat akun</button>
                    </form>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
