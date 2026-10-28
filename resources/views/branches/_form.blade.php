@php($values = old('_form') === $formKey ? old() : [])
<form method="POST" action="{{ $creating ? route('branches.store') : route('branches.update', $branch) }}" class="space-y-4" data-branch-form>
    @csrf @if(! $creating) @method('PUT') @endif
    <input type="hidden" name="_form" value="{{ $formKey }}">
    @if(old('_form') === $formKey && $errors->any())<div class="rounded-md bg-rose-50 p-3 text-sm text-rose-800" role="alert">{{ $errors->first() }}</div>@endif
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-semibold">Kode<input name="code" value="{{ old('_form') === $formKey ? old('code') : ($branch->code ?? '') }}" required maxlength="30" pattern="[A-Za-z0-9_-]+" class="pos-input mt-1">@if(old('_form') === $formKey)@error('code')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold">Nama<input name="name" value="{{ old('_form') === $formKey ? old('name') : ($branch->name ?? '') }}" required maxlength="150" class="pos-input mt-1">@if(old('_form') === $formKey)@error('name')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold sm:col-span-2">Alamat<input name="address" value="{{ old('_form') === $formKey ? old('address') : ($branch->address ?? '') }}" maxlength="255" class="pos-input mt-1">@if(old('_form') === $formKey)@error('address')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold">Telepon<input name="phone" value="{{ old('_form') === $formKey ? old('phone') : ($branch->phone ?? '') }}" maxlength="50" class="pos-input mt-1">@if(old('_form') === $formKey)@error('phone')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
        <label class="block text-sm font-semibold">Zona waktu<input name="timezone" value="{{ old('_form') === $formKey ? old('timezone', 'Asia/Jakarta') : ($branch->timezone ?? 'Asia/Jakarta') }}" required list="branch-timezones" class="pos-input mt-1">@if(old('_form') === $formKey)@error('timezone')<span class="mt-1 block text-xs text-rose-700">{{ $message }}</span>@enderror @endif</label>
    </div>
    <datalist id="branch-timezones"><option value="Asia/Jakarta"><option value="Asia/Makassar"><option value="Asia/Jayapura"></datalist>
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" @checked(old('_form') === $formKey ? (bool) old('is_active') : ($branch->is_active ?? true)) class="rounded border-slate-300 text-emerald-700"> Cabang aktif</label>
    @if(old('_form') === $formKey)@error('is_active')<span class="block text-xs text-rose-700">{{ $message }}</span>@enderror @endif
    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end"><button type="button" data-dialog-close class="pos-button w-auto border border-slate-300 bg-white text-slate-700">Batal</button><button type="submit" class="pos-button w-auto bg-emerald-700 text-white" data-submit-button>{{ $creating ? 'Simpan Cabang' : 'Simpan Perubahan' }}</button></div>
</form>
