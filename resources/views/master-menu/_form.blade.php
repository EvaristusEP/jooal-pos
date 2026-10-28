@php
    $formMarker = $formMarker ?? (($formMethod ?? 'PUT') === 'POST' ? 'menu-create' : 'menu-edit-'.$menu->id);
    $useOld = old('_form') === $formMarker;
@endphp
<form method="POST" action="{{ $formAction }}" class="space-y-6">
    @csrf
    <input type="hidden" name="_form" value="{{ $formMarker }}">
    @if (($formMethod ?? 'PUT') !== 'POST') @method($formMethod ?? 'PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-bold text-slate-700">Kategori
            <select name="category_id" required class="pos-input mt-2">
                @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(($useOld ? old('category_id', $menu->category_id) : $menu->category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-bold text-slate-700">Nama
            <input name="name" value="{{ $useOld ? old('name', $menu->name) : $menu->name }}" required maxlength="150" class="pos-input mt-2">
        </label>
        <label class="block text-sm font-bold text-slate-700">Slug
            <input name="slug" value="{{ $useOld ? old('slug', $menu->slug) : $menu->slug }}" maxlength="150" class="pos-input mt-2">
        </label>
        <label class="block text-sm font-bold text-slate-700">Harga standar
            <input type="number" name="price" value="{{ $useOld ? old('price', $menu->price) : $menu->price }}" min="0" step="0.01" required class="pos-input mt-2">
        </label>
    </div>

    <label class="block text-sm font-bold text-slate-700">Deskripsi
        <textarea name="description" rows="3" class="pos-input mt-2">{{ $useOld ? old('description', $menu->description) : $menu->description }}</textarea>
    </label>
    <label class="block text-sm font-bold text-slate-700">Tags produk
        <input type="hidden" name="sync_tag_ids" value="1">
        <select name="tag_ids[]" multiple class="pos-input mt-2 min-h-28">
            @foreach ($tags as $tag)
            <option value="{{ $tag->id }}" @selected(in_array($tag->id, $useOld ? old('tag_ids', $menu->exists ? $menu->tags->pluck('id')->all() : []) : ($menu->exists ? $menu->tags->pluck('id')->all() : [])))>{{ $tag->name }}{{ $tag->is_active ? '' : ' (nonaktif)' }}</option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs font-normal text-slate-500">Pilih satu atau beberapa tag. Kelola tag melalui Master Data → Tags.</span>
    </label>
    <label class="flex items-center gap-3 text-sm font-bold text-slate-700">
        <input type="hidden" name="is_active" value="0">
        @if (($formMethod ?? 'PUT') === 'POST')
            <input type="hidden" name="is_active" value="0"><span>Produk baru disimpan nonaktif sampai resep dilengkapi.</span>
        @else
            <input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($useOld ? old('is_active', $menu->is_active) : $menu->is_active) class="rounded border-slate-300 text-emerald-700"> Produk aktif
        @endif
    </label>
    <div class="border-t border-slate-100 pt-5">
        <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Varian produk</p>
        <div class="mt-3 space-y-3">
            @foreach ($menu->variants as $index => $variant)
                <div class="grid gap-2 rounded-2xl border border-slate-100 p-3 sm:grid-cols-[minmax(0,1fr)_150px_auto]">
                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant->id }}">
                    <input name="variants[{{ $index }}][name]" value="{{ $variant->name }}" class="pos-input">
                    <input type="number" name="variants[{{ $index }}][price_adjustment]" value="{{ $variant->price_adjustment }}" min="0" step="0.01" class="pos-input">
                    <label class="flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="variants[{{ $index }}][is_active]" value="0"><input type="checkbox" name="variants[{{ $index }}][is_active]" value="1" @checked($variant->is_active) class="rounded border-slate-300 text-emerald-700"> Aktif</label>
                </div>
            @endforeach
            @for ($index = $menu->variants->count(); $index < $menu->variants->count() + 2; $index++)
                <div class="grid gap-2 rounded-2xl border border-dashed border-slate-200 p-3 sm:grid-cols-[minmax(0,1fr)_150px_auto]">
                    <input name="variants[{{ $index }}][name]" placeholder="Varian baru" class="pos-input">
                    <input type="number" name="variants[{{ $index }}][price_adjustment]" value="0" min="0" step="0.01" class="pos-input">
                    <label class="flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="variants[{{ $index }}][is_active]" value="0"><input type="checkbox" name="variants[{{ $index }}][is_active]" value="1" checked class="rounded border-slate-300 text-emerald-700"> Aktif</label>
                </div>
            @endfor
        </div>
    </div>

    <div class="border-t border-slate-100 pt-5">
        <p class="text-xs font-bold uppercase tracking-[.18em] text-emerald-700">Harga dan ketersediaan per cabang</p>
        <p class="mt-1 text-sm text-slate-500">Kosongkan harga untuk memakai harga standar pusat.</p>
        <div class="mt-3 space-y-3">
            @forelse ($branches as $branch)
                @php $setting = $menu->branches->firstWhere('id', $branch->id); @endphp
                <div class="grid gap-3 rounded-2xl border border-slate-100 p-3 sm:grid-cols-[minmax(0,1fr)_170px_auto]">
                    <div><p class="font-bold">{{ $branch->display_name }}</p><p class="text-xs {{ $branch->is_active ? 'text-emerald-700' : 'text-rose-600' }}">{{ $branch->is_active ? 'Cabang aktif' : 'Cabang nonaktif' }}</p></div>
                    <input type="number" name="branch_settings[{{ $branch->id }}][price]" value="{{ optional(optional($setting)->pivot)->price }}" placeholder="Harga standar" min="0" step="0.01" class="pos-input">
                    <label class="flex items-center gap-2 text-xs font-semibold"><input type="hidden" name="branch_settings[{{ $branch->id }}][is_available]" value="0"><input type="checkbox" name="branch_settings[{{ $branch->id }}][is_available]" value="1" @checked(optional(optional($setting)->pivot)->is_available ?? true) class="rounded border-slate-300 text-emerald-700"> Tersedia</label>
                </div>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Belum ada cabang.</p>
            @endforelse
        </div>
    </div>

    <button class="pos-button w-full bg-emerald-700 text-white hover:bg-emerald-800">{{ ($formMethod ?? 'PUT') === 'POST' ? 'Buat produk' : 'Simpan perubahan' }}</button>
</form>
