@php($editing = old('_form') === 'table-edit-'.$table->id)
<x-crud-dialog id="table-edit-{{ $table->id }}" title="Edit Meja" description="Perubahan berlaku untuk kapasitas dan nama meja di cabang terkait." :open-on-load="$editing">
    <form method="POST" action="{{ route('tables.update', $table) }}" class="space-y-4">@csrf @method('PUT')<input type="hidden" name="_form" value="table-edit-{{ $table->id }}">
        @if($editing && $errors->any())<div class="rounded-md bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <label class="block text-sm font-semibold">Nama meja<input name="name" value="{{ $editing ? old('name', $table->name) : $table->name }}" required maxlength="80" class="pos-input mt-1">@if($editing)@error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold">Kapasitas<input type="number" name="capacity" value="{{ $editing ? old('capacity', $table->capacity) : $table->capacity }}" min="1" max="500" required class="pos-input mt-1">@if($editing)@error('capacity')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end"><button type="button" data-dialog-close class="pos-button border border-slate-300 bg-white text-slate-700">Batal</button><button class="pos-button bg-emerald-700 text-white">Simpan</button></div>
    </form>
</x-crud-dialog>
