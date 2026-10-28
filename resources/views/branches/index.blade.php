<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Manajemen Cabang · JOOAL POS</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased">
<x-pos-sidebar />
<main class="min-h-screen min-w-0 p-4 lg:p-6"><div class="mx-auto w-full max-w-6xl space-y-5">
    <header class="pos-page-header flex flex-col gap-4 bg-white sm:flex-row sm:items-center sm:justify-between">
        <div><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Owner</p><h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Manajemen Cabang</h1><p class="mt-1 text-sm text-slate-500">Kelola cabang tanpa menghapus data historis.</p></div>
        <button type="button" data-dialog-open="branch-create-dialog" class="pos-button w-auto shrink-0 bg-emerald-700 text-white">+ Tambah Cabang</button>
    </header>
    @if(session('success'))<div class="rounded-md bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{{ session('success') }}</div>@endif
    @if($errors->any() && ! old('_form'))<div class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif
    <section id="branch-list" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 sm:p-5" aria-label="Daftar cabang">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Daftar</p><h2 class="mt-1 text-lg font-bold">Cabang JOOAL</h2></div><div class="flex items-end gap-3"><label class="min-w-0 flex-1 text-xs font-semibold sm:w-64">Cari cabang<input type="search" data-filter-search="branch-list" class="pos-input mt-1" placeholder="Kode atau nama"></label><span class="whitespace-nowrap rounded-md bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-800">{{ $branches->count() }} cabang</span></div></div>
        @forelse($branches as $branch)
            <article data-filter-item data-filter-text="{{ mb_strtolower($branch->code.' '.$branch->name.' '.$branch->address) }}" class="rounded-md border border-slate-200 p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="font-bold text-slate-950">{{ $branch->display_name }}</h3><span class="rounded px-2 py-1 text-xs font-bold {{ $branch->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}</span></div><p class="mt-1 break-words text-sm text-slate-500">{{ $branch->address ?: 'Alamat belum diisi' }} · {{ $branch->timezone }}</p><p class="mt-1 text-xs text-slate-400">{{ $branch->users_count }} user terhubung</p></div><div class="flex shrink-0 gap-2"><button type="button" data-dialog-open="branch-edit-{{ $branch->id }}" class="pos-button w-auto border border-slate-300 bg-white text-slate-700">Edit</button><form method="POST" action="{{ route('branches.toggle-status', $branch) }}" onsubmit="return confirm('Yakin {{ $branch->is_active ? 'menonaktifkan' : 'mengaktifkan' }} cabang {{ addslashes($branch->display_name) }}? Cabang yang nonaktif tidak menerima transaksi baru.')">@csrf @method('PATCH')<button type="submit" class="pos-button w-auto {{ $branch->is_active ? 'bg-rose-50 text-rose-700' : 'bg-emerald-700 text-white' }}">{{ $branch->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></div></div></article>
            <x-crud-dialog id="branch-edit-{{ $branch->id }}" title="Edit Cabang" description="Perubahan cabang tidak mengubah snapshot transaksi yang sudah tersimpan." :open-on-load="old('_form') === 'branch-edit-'.$branch->id && $errors->any()">
                @include('branches._form', ['branch' => $branch, 'formKey' => 'branch-edit-'.$branch->id, 'creating' => false])
            </x-crud-dialog>
        @empty<div class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">Belum ada cabang.</div>@endforelse
        <p data-filter-empty class="hidden rounded-md border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Tidak ada cabang yang cocok dengan pencarian.</p>
    </section>
</div></main>
<x-crud-dialog id="branch-create-dialog" title="Tambah Cabang" description="Kode cabang harus unik. Pastikan zona waktu sesuai lokasi operasional." :open-on-load="old('_form') === 'branch-create' && $errors->any()">
    @include('branches._form', ['branch' => null, 'formKey' => 'branch-create', 'creating' => true])
</x-crud-dialog>
</body></html>
