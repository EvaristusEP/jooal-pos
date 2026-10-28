<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengaturan · JOOAL POS</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f5f8f5] font-sans text-slate-900">
    <x-pos-sidebar />
    <main class="min-h-screen w-full min-w-0 p-4 transition-[margin] duration-300 lg:ml-20 lg:p-6">
        <div class="w-full min-w-0 space-y-5">
            <header class="pos-page-header rounded-3xl bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Administrasi · JOOAL POS</p>
                <h1 class="mt-1 text-2xl font-extrabold">Pengaturan</h1>
                <p class="mt-1 text-sm text-slate-500">Kelola preferensi tampilan dan bahasa. Konfigurasi server tetap dikelola oleh administrator teknis.</p>
            </header>

            <section class="rounded-2xl border bg-white p-5">
                <h2 class="text-lg font-bold">Informasi aplikasi</h2>
                <dl class="mt-3 divide-y">
                    @foreach($settings as $setting)
                        <div class="grid min-w-0 grid-cols-1 gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-3">
                            <dt class="min-w-0 break-words text-slate-600">{{ $setting['label'] }}</dt>
                            <dd class="min-w-0 break-words font-semibold sm:text-right">{{ $setting['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="rounded-2xl border bg-white p-5">
                <h2 class="text-lg font-bold">Tema tampilan</h2>
                <p class="mt-1 text-sm text-slate-500">Pilihan disimpan di browser ini dan tetap aktif saat berpindah halaman.</p>
                <label class="mt-3 block max-w-xl text-sm font-semibold">Pilih tema
                    <select data-theme-select class="pos-input mt-1">
                        <option value="light">Terang</option>
                        <option value="dark-teal">Dark Teal · hijau teal gelap</option>
                        <option value="slate-night">Slate Night · abu kebiruan dan aksen biru</option>
                        <option value="coffee-night">Coffee Night · cokelat dan aksen karamel</option>
                    </select>
                </label>
                <p class="mt-2 text-xs text-slate-500">Tema awal untuk browser baru: Terang. Pilihan disimpan pada browser ini.</p>
            </section>

            <section class="rounded-2xl border bg-white p-5">
                <h2 class="text-lg font-bold">Language / Bahasa</h2>
                <form method="POST" action="{{ route('settings.system.locale') }}" class="mt-3 grid min-w-0 grid-cols-1 items-end gap-3 sm:flex sm:flex-wrap">
                    @csrf
                    <label class="block min-w-0 w-full text-sm font-semibold sm:max-w-sm">Language
                        <select name="locale" class="pos-input mt-1">
                            <option value="id" @selected(app()->getLocale()==='id')>Bahasa Indonesia</option>
                            <option value="en" @selected(app()->getLocale()==='en')>English</option>
                        </select>
                    </label>
                    <button class="pos-button w-full bg-emerald-700 text-white sm:w-auto">Save language</button>
                </form>
                <p class="mt-2 text-xs text-slate-500">Preference is saved in this browser; application defaults are unchanged.</p>
            </section>

            <section class="rounded-2xl border bg-white p-5">
                <h2 class="text-lg font-bold">Email</h2>
                <p class="mt-2">Mailer aktif: <strong>{{ $mailStatus['mailer'] }}</strong> ({{ $mailStatus['transport'] }})</p>
                <p class="mt-2 break-words {{ $mailStatus['configured'] ? 'text-emerald-800' : 'text-amber-800' }}">{{ $mailStatus['message'] }}</p>
            </section>
            <details class="rounded-lg border border-slate-200 bg-white p-4"><summary class="cursor-pointer text-sm font-semibold">Informasi teknis</summary><dl class="mt-3 divide-y">@foreach($technicalSettings as $setting)<div class="grid grid-cols-2 gap-3 py-2 text-sm"><dt class="text-slate-500">{{ $setting['label'] }}</dt><dd class="text-right font-medium">{{ $setting['value'] }}</dd></div>@endforeach</dl><p class="mt-2 text-xs text-slate-500">Nama aplikasi dan zona waktu bersumber dari konfigurasi runtime; nilai rahasia tidak ditampilkan.</p></details>
        </div>
    </main>
</body>
</html>
