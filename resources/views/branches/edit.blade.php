<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Edit Cabang · JOOAL POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900 antialiased">
    <x-pos-sidebar />
    <main class="min-h-screen p-4 transition-[margin] duration-300 lg:ml-20 lg:p-6">
        <section class="mx-auto max-w-2xl rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-100">
            <a href="{{ route('branches.index') }}" class="text-sm font-bold text-emerald-700">← Kembali ke cabang</a>
            <p class="mt-6 text-xs font-bold uppercase tracking-[.2em] text-emerald-700">Owner</p>
            <h1 class="mt-1 text-3xl font-extrabold text-[#123c35]">Edit Cabang</h1>

            @if ($errors->any())
                <div class="mt-5 rounded-2xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('branches.update', $branch) }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')
                <label class="block text-sm font-bold text-slate-700">Kode<input name="code" value="{{ old('code', $branch->code) }}" required maxlength="30" class="pos-input mt-2"></label>
                <label class="block text-sm font-bold text-slate-700">Nama<input name="name" value="{{ old('name', $branch->name) }}" required maxlength="150" class="pos-input mt-2"></label>
                <label class="block text-sm font-bold text-slate-700">Alamat<input name="address" value="{{ old('address', $branch->address) }}" maxlength="255" class="pos-input mt-2"></label>
                <label class="block text-sm font-bold text-slate-700">Telepon<input name="phone" value="{{ old('phone', $branch->phone) }}" maxlength="50" class="pos-input mt-2"></label>
                <label class="block text-sm font-bold text-slate-700">Timezone<input name="timezone" value="{{ old('timezone', $branch->timezone) }}" required class="pos-input mt-2"></label>
                <label class="flex items-center gap-3 text-sm font-bold text-slate-700"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $branch->is_active)) class="rounded border-slate-300 text-emerald-700"> Cabang aktif</label>
                <button type="submit" class="pos-button w-full bg-emerald-700 text-white hover:bg-emerald-800">Simpan perubahan</button>
            </form>
        </section>
    </main>
</body>
</html>
