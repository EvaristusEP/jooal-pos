<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Add-on · JOOAL POS</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased"><x-pos-sidebar />
<main class="min-h-screen min-w-0 p-4 lg:p-6"><div class="mx-auto w-full max-w-6xl min-w-0 space-y-5">
    <header class="pos-page-header bg-white"><p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Master Data · JOOAL POS</p><h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Add-on</h1><p class="mt-1 text-sm text-slate-500">Kelola topping yang digunakan POS dan resep bahan baku.</p></header>
    @if (session('success'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif
    <div class="flex min-w-0 flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-end sm:justify-between">
        <label class="block min-w-0 w-full text-sm font-semibold sm:flex-1 sm:whitespace-nowrap">Cari add-on<input type="search" data-filter-search="addon-list" class="pos-input mt-1" placeholder="Nama atau slug"></label>
        <button type="button" data-dialog-open="addon-create-dialog" class="pos-button w-auto shrink-0 self-end whitespace-nowrap bg-emerald-700 text-white"><span aria-hidden="true">+</span>&nbsp; Tambah Add-on</button>
    </div>
    <section id="addon-list" class="min-w-0 space-y-3" aria-label="Daftar add-on">
        @forelse ($addons as $addon)
            @php
                $editing = old('_form') === 'addon-edit-'.$addon->id;
            @endphp
            <article data-filter-item data-filter-text="{{ mb_strtolower($addon->name.' '.$addon->slug) }}" class="flex min-w-0 flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h2 class="break-words font-bold">{{ $addon->name }}</h2><span class="rounded-md px-2 py-1 text-xs font-bold {{ $addon->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $addon->is_active ? 'Aktif' : 'Nonaktif' }}</span></div><p class="mt-1 break-all text-sm text-slate-600">Rp {{ number_format($addon->price, 0, ',', '.') }} · {{ $addon->slug }}</p></div>
                <div class="flex shrink-0 flex-wrap gap-2"><button type="button" data-dialog-open="addon-edit-{{ $addon->id }}" class="pos-button border border-slate-300 bg-white px-3 text-slate-700" aria-label="Edit add-on {{ $addon->name }}">✎ <span class="sr-only">Edit</span></button><form method="POST" action="{{ route('addons.toggle-status', $addon) }}" onsubmit="return confirm('{{ $addon->is_active ? 'Nonaktifkan add-on ini?' : 'Aktifkan add-on ini?' }}')">@csrf @method('PATCH')<button class="pos-button px-3 {{ $addon->is_active ? 'bg-rose-50 text-rose-700' : 'bg-emerald-700 text-white' }}">{{ $addon->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></div>
            </article>
            <x-crud-dialog id="addon-edit-{{ $addon->id }}" title="Edit Add-on" description="Perbarui nama, slug, dan harga topping." :open-on-load="$editing">
                <form method="POST" action="{{ route('addons.update', $addon) }}" class="space-y-4">@csrf @method('PUT')<input type="hidden" name="_form" value="addon-edit-{{ $addon->id }}">
                    @if($editing && $errors->any())<div class="rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
                    <label class="block text-sm font-semibold">Nama<input name="name" value="{{ $editing ? old('name', $addon->name) : $addon->name }}" required maxlength="150" class="pos-input mt-1">@if($editing)@error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
                    <label class="block text-sm font-semibold">Slug (opsional)<input name="slug" value="{{ $editing ? old('slug', $addon->slug) : $addon->slug }}" maxlength="150" class="pos-input mt-1">@if($editing)@error('slug')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
                    <label class="block text-sm font-semibold">Harga<input type="number" name="price" value="{{ $editing ? old('price', $addon->price) : $addon->price }}" min="0" max="99999999.99" step="0.01" required class="pos-input mt-1">@if($editing)@error('price')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end"><button type="button" data-dialog-close class="pos-button border border-slate-300 bg-white text-slate-700">Batal</button><button class="pos-button bg-emerald-700 text-white">Simpan</button></div>
                </form>
            </x-crud-dialog>
        @empty
            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Belum ada add-on. Klik <strong>Tambah Add-on</strong> untuk menambahkan pilihan POS.</div>
        @endforelse
        <p data-filter-empty class="hidden rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">Tidak ada add-on yang cocok dengan pencarian.</p>
    </section>
</div></main>
<x-crud-dialog id="addon-create-dialog" title="Tambah Add-on" description="Add-on menggunakan entitas topping yang sudah ada." :open-on-load="old('_form') === 'addon-create' && $errors->any()">
    <form method="POST" action="{{ route('addons.store') }}" class="space-y-4">@csrf<input type="hidden" name="_form" value="addon-create">
        @if(old('_form') === 'addon-create' && $errors->any())<div class="rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <label class="block text-sm font-semibold">Nama<input name="name" value="{{ old('_form') === 'addon-create' ? old('name') : '' }}" required maxlength="150" class="pos-input mt-1">@if(old('_form') === 'addon-create')@error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold">Slug (opsional)<input name="slug" value="{{ old('_form') === 'addon-create' ? old('slug') : '' }}" maxlength="150" class="pos-input mt-1">@if(old('_form') === 'addon-create')@error('slug')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold">Harga<input type="number" name="price" value="{{ old('_form') === 'addon-create' ? old('price', '0.00') : '0.00' }}" min="0" max="99999999.99" step="0.01" required class="pos-input mt-1">@if(old('_form') === 'addon-create')@error('price')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end"><button type="button" data-dialog-close class="pos-button border border-slate-300 bg-white text-slate-700">Batal</button><button class="pos-button bg-emerald-700 text-white">Simpan Add-on</button></div>
    </form>
</x-crud-dialog>
</body></html>
