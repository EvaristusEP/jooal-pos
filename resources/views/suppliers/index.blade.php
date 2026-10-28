<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Pemasok · JOOAL POS</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased"><x-pos-sidebar />
<main class="min-h-screen min-w-0 p-4 lg:p-6"><div class="mx-auto w-full max-w-6xl min-w-0 space-y-5">
    <header class="pos-page-header bg-white"><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Inventory · JOOAL POS</p><h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Pemasok</h1><p class="mt-1 text-sm text-slate-500">Pemasok terpusat yang ditautkan ke dokumen penerimaan stok.</p></header>
    @if (session('success'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif
    <div class="flex min-w-0 flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-end sm:justify-between"><label class="block min-w-0 w-full text-sm font-semibold sm:flex-1 sm:whitespace-nowrap">Cari pemasok<input type="search" data-filter-search="supplier-list" class="pos-input mt-1" placeholder="Nama, kontak, telepon, atau email"></label>@if(auth()->user()->isOwner())<button type="button" data-dialog-open="supplier-create-dialog" class="pos-button w-auto shrink-0 self-end whitespace-nowrap bg-emerald-700 text-white"><span aria-hidden="true">+</span>&nbsp; Tambah Pemasok</button>@endif</div>
    <section id="supplier-list" class="min-w-0 space-y-3" aria-label="Daftar pemasok">
        @forelse ($suppliers as $supplier)
            @php
                $editing = old('_form') === 'supplier-edit-'.$supplier->id;
            @endphp
            <article data-filter-item data-filter-text="{{ mb_strtolower($supplier->name.' '.$supplier->contact_name.' '.$supplier->phone.' '.$supplier->email) }}" class="flex min-w-0 flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="break-words font-bold">{{ $supplier->name }}</h2><span class="rounded-md px-2 py-1 text-xs font-bold {{ $supplier->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $supplier->is_active ? 'Aktif' : 'Nonaktif' }}</span><span class="text-xs text-slate-500">{{ $supplier->stock_receipts_count }} penerimaan</span></div><p class="mt-1 break-words text-sm text-slate-600">{{ $supplier->contact_name ?: 'Kontak belum diisi' }} · {{ $supplier->phone ?: 'Telepon belum diisi' }} · {{ $supplier->email ?: 'Email belum diisi' }}</p><p class="mt-1 break-words text-xs text-slate-500">{{ $supplier->address ?: 'Alamat belum diisi' }}</p></div>
                @if (auth()->user()->isOwner())<div class="flex shrink-0 flex-wrap gap-2"><button type="button" data-dialog-open="supplier-edit-{{ $supplier->id }}" class="pos-button border border-slate-300 bg-white px-3 text-slate-700" aria-label="Edit pemasok {{ $supplier->name }}">✎ <span class="sr-only">Edit</span></button><form method="POST" action="{{ route('suppliers.toggle-status', $supplier) }}" onsubmit="return confirm('{{ $supplier->is_active ? 'Nonaktifkan pemasok ini?' : 'Aktifkan pemasok ini?' }}')">@csrf @method('PATCH')<button class="pos-button px-3 {{ $supplier->is_active ? 'bg-rose-50 text-rose-700' : 'bg-emerald-700 text-white' }}">{{ $supplier->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></div>@endif
            </article>
        @empty<div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Belum ada pemasok. @if(auth()->user()->isOwner())Klik <strong>Tambah Pemasok</strong> untuk membuat pemasok pertama.@endif</div>@endforelse
        <p data-filter-empty class="hidden rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">Tidak ada pemasok yang cocok dengan pencarian.</p>
    </section>
    @if(auth()->user()->isOwner())@foreach($suppliers as $supplier) @include('suppliers._edit-dialog', ['supplier' => $supplier]) @endforeach @endif
</div></main>
@if(auth()->user()->isOwner())
<x-crud-dialog id="supplier-create-dialog" title="Tambah Pemasok" description="Pemasok yang sudah digunakan tidak dihapus; nonaktifkan agar tidak muncul pada penerimaan baru." :open-on-load="old('_form') === 'supplier-create' && $errors->any()">
    <form method="POST" action="{{ route('suppliers.store') }}" class="space-y-4">@csrf<input type="hidden" name="_form" value="supplier-create">
        @if(old('_form') === 'supplier-create' && $errors->any())<div class="rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <label class="block text-sm font-semibold">Nama pemasok *<input name="name" value="{{ old('_form') === 'supplier-create' ? old('name') : '' }}" required maxlength="150" class="pos-input mt-1">@if(old('_form') === 'supplier-create')@error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-semibold">Nama kontak<input name="contact_name" value="{{ old('_form') === 'supplier-create' ? old('contact_name') : '' }}" maxlength="150" class="pos-input mt-1"></label><label class="block text-sm font-semibold">Telepon<input name="phone" value="{{ old('_form') === 'supplier-create' ? old('phone') : '' }}" maxlength="50" class="pos-input mt-1"></label></div>
        <label class="block text-sm font-semibold">Email<input type="email" name="email" value="{{ old('_form') === 'supplier-create' ? old('email') : '' }}" maxlength="190" class="pos-input mt-1">@if(old('_form') === 'supplier-create')@error('email')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold">Alamat<textarea name="address" rows="2" maxlength="5000" class="pos-input mt-1">{{ old('_form') === 'supplier-create' ? old('address') : '' }}</textarea></label><label class="block text-sm font-semibold">Catatan<textarea name="notes" rows="2" maxlength="5000" class="pos-input mt-1">{{ old('_form') === 'supplier-create' ? old('notes') : '' }}</textarea></label>
        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end"><button type="button" data-dialog-close class="pos-button border border-slate-300 bg-white text-slate-700">Batal</button><button class="pos-button bg-emerald-700 text-white">Simpan Pemasok</button></div>
    </form>
</x-crud-dialog>
@endif
</body></html>
