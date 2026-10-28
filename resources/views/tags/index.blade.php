<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tags · JOOAL POS</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900"><x-pos-sidebar />
<main class="min-h-screen min-w-0 p-4 lg:p-6"><div class="mx-auto w-full max-w-6xl min-w-0 space-y-5">
    <header class="pos-page-header bg-white"><p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Master Data · JOOAL POS</p><h1 class="mt-1 text-2xl font-extrabold text-[#123c35]">Tags Produk</h1><p class="mt-1 text-sm text-slate-500">Tag mengelompokkan produk/menu dan dapat dipakai oleh beberapa produk.</p></header>
    @if(session('success'))<p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert">{{ $errors->first() }}</div>@endif
    <div class="flex min-w-0 flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:flex-row sm:items-end sm:justify-between">
        <label class="block min-w-0 w-full text-sm font-semibold sm:flex-1 sm:whitespace-nowrap">Cari tag
            <input type="search" data-filter-search="tag-list" class="pos-input mt-1" placeholder="Nama atau slug">
        </label>
        <button type="button" data-dialog-open="tag-create-dialog" class="pos-button w-auto shrink-0 self-end whitespace-nowrap bg-emerald-700 text-white"><span aria-hidden="true">+</span>&nbsp; Tambah Tag</button>
    </div>
    <section id="tag-list" class="min-w-0 space-y-3" aria-label="Daftar tag">
        @forelse($tags as $tag)
            @php
                $editing = old('_form') === 'tag-edit-'.$tag->id;
            @endphp
            <article data-filter-item data-filter-text="{{ mb_strtolower($tag->name.' '.$tag->slug) }}" class="grid min-w-0 gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                <div class="min-w-0"><h2 class="break-words font-bold text-[#123c35]">{{ $tag->name }}</h2><p class="mt-1 break-all text-sm text-slate-500">{{ $tag->slug }}</p><p class="mt-1 text-xs text-slate-500">{{ $tag->menus_count }} produk · <span class="font-semibold {{ $tag->is_active ? 'text-emerald-700' : 'text-slate-500' }}">{{ $tag->is_active ? 'Aktif' : 'Nonaktif' }}</span></p></div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-dialog-open="tag-edit-{{ $tag->id }}" class="pos-button border border-slate-300 bg-white px-3 text-slate-700" aria-label="Edit tag {{ $tag->name }}">✎ <span class="sr-only">Edit</span></button>
                    <form method="POST" action="{{ route('tags.toggle-status', $tag) }}" onsubmit="return confirm('{{ $tag->is_active ? 'Nonaktifkan tag ini?' : 'Aktifkan tag ini?' }}')">@csrf @method('PATCH')<button class="pos-button px-3 {{ $tag->is_active ? 'bg-rose-50 text-rose-800' : 'bg-emerald-700 text-white' }}">{{ $tag->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                </div>
            </article>
            <x-crud-dialog id="tag-edit-{{ $tag->id }}" title="Edit Tag" description="Perubahan nama dan slug berlaku pada produk yang menggunakan tag ini." :open-on-load="$editing">
                <form method="POST" action="{{ route('tags.update', $tag) }}" class="space-y-4">@csrf @method('PUT')<input type="hidden" name="_form" value="tag-edit-{{ $tag->id }}">
                    @if($editing && $errors->any())<div class="rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
                    <label class="block text-sm font-semibold">Nama<input name="name" value="{{ $editing ? old('name', $tag->name) : $tag->name }}" required maxlength="100" class="pos-input mt-1">@if($editing)@error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
                    <label class="block text-sm font-semibold">Slug (opsional)<input name="slug" value="{{ $editing ? old('slug', $tag->slug) : $tag->slug }}" maxlength="120" class="pos-input mt-1">@if($editing)@error('slug')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end"><button type="button" data-dialog-close class="pos-button border border-slate-300 bg-white text-slate-700">Batal</button><button class="pos-button bg-emerald-700 text-white">Simpan</button></div>
                </form>
            </x-crud-dialog>
        @empty
            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">Belum ada tag. Klik <strong>Tambah Tag</strong> untuk membuat tag pertama.</div>
        @endforelse
        <p data-filter-empty class="hidden rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">Tidak ada tag yang cocok dengan pencarian.</p>
    </section>
</div></main>
<x-crud-dialog id="tag-create-dialog" title="Tambah Tag" description="Nama dan slug harus unik." :open-on-load="old('_form') === 'tag-create' && $errors->any()">
    <form method="POST" action="{{ route('tags.store') }}" class="space-y-4">@csrf<input type="hidden" name="_form" value="tag-create">
        @if(old('_form') === 'tag-create' && $errors->any())<div class="rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <label class="block text-sm font-semibold">Nama<input name="name" value="{{ old('_form') === 'tag-create' ? old('name') : '' }}" required maxlength="100" class="pos-input mt-1">@if(old('_form') === 'tag-create')@error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold">Slug (opsional)<input name="slug" value="{{ old('_form') === 'tag-create' ? old('slug') : '' }}" maxlength="120" class="pos-input mt-1">@if(old('_form') === 'tag-create')@error('slug')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end"><button type="button" data-dialog-close class="pos-button border border-slate-300 bg-white text-slate-700">Batal</button><button class="pos-button bg-emerald-700 text-white">Simpan Tag</button></div>
    </form>
</x-crud-dialog>
</body></html>
