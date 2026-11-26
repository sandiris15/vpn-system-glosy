@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'preferences'])

@section('title', 'Preferensi')
@section('breadcrumb', 'Preferensi')
@section('mobile-heading', 'Preferensi')

@section('content')
    <div class="mb-7"><p class="text-sm text-slate-400">Pengalaman workspace</p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-white">Preferensi tampilan</h1><p class="mt-2 text-sm text-slate-400">Atur wallpaper dan nuansa dashboard NOC.</p></div>
    <form method="POST" action="/preferences" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]" x-data="{ previewUrl: null, blur: {{ (int) old('blur', $preference->blur ?? 8) }}, overlay: {{ (int) old('overlay', $preference->overlay ?? 45) }} }">
        @csrf
        @method('PUT')
        <section class="glass-panel p-5 sm:p-7">
            <div class="mb-6"><h2 class="font-semibold text-white">Wallpaper</h2><p class="mt-1 text-xs text-slate-400">Pilih preset atau unggah gambar JPG/PNG.</p></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach(($wallpapers ?? []) as $wallpaper)
                    <label class="group cursor-pointer">
                        <input class="peer sr-only" type="radio" name="wallpaper" value="{{ data_get($wallpaper, 'id') }}" @checked(old('wallpaper', $preference->wallpaper_id ?? null) == data_get($wallpaper, 'id'))>
                        <span class="wallpaper-preview block" style="background-image: linear-gradient(135deg, rgba(15,23,42,.05), rgba(15,23,42,.55)), url('{{ data_get($wallpaper, 'preview_url') }}')">
                            <span class="mt-auto flex items-center justify-between text-xs font-medium text-white"><span>{{ data_get($wallpaper, 'name', 'Preset') }}</span><span class="checkmark">✓</span></span>
                        </span>
                    </label>
                @endforeach
                @if(count($wallpapers ?? []) === 0)
                    <div class="col-span-full rounded-2xl border border-dashed border-white/15 p-7 text-center text-sm text-slate-400">Preset wallpaper belum tersedia.</div>
                @endif
            </div>
            <div class="mt-6">
                <label for="wallpaper_upload" class="field-label">Unggah wallpaper</label>
                <input id="wallpaper_upload" name="wallpaper_upload" type="file" accept="image/jpeg,image/png" class="file-input" @change="const file = $event.target.files[0]; if (file) previewUrl = URL.createObjectURL(file)">
                <p class="field-hint">JPG atau PNG. Ukuran dan MIME type akan divalidasi server sebelum disimpan.</p>
                @error('wallpaper_upload')<p class="field-error">{{ $message }}</p>@enderror
                <div x-cloak x-show="previewUrl" class="mt-4">
                    <p class="field-label">Preview wallpaper</p>
                    <div class="relative min-h-40 overflow-hidden rounded-2xl border border-white/10">
                        <div class="absolute -inset-3 bg-cover bg-center transition" :style="`background-image: url('${previewUrl}'); filter: blur(${blur}px); transform: scale(1.04)`"></div>
                        <div class="absolute inset-0 bg-slate-950 transition-opacity" :style="`opacity: ${overlay / 100}`"></div>
                        <div class="relative grid min-h-40 place-items-center text-sm font-medium text-white">Preview dashboard</div>
                    </div>
                </div>
            </div>
        </section>
        <aside class="space-y-4">
            <section class="glass-panel p-5 sm:p-6">
                <h2 class="font-semibold text-white">Penyesuaian</h2>
                <div class="mt-5">
                    <label for="blur" class="field-label">Wallpaper blur <span class="float-right text-slate-400"><span x-text="blur"></span> px</span></label>
                    <input id="blur" name="blur" type="range" min="0" max="24" x-model="blur" value="{{ old('blur', $preference->blur ?? 8) }}" class="range-input">
                </div>
                <div class="mt-6">
                    <label for="overlay" class="field-label">Overlay gelap <span class="float-right text-slate-400"><span x-text="overlay"></span>%</span></label>
                    <input id="overlay" name="overlay" type="range" min="0" max="80" x-model="overlay" value="{{ old('overlay', $preference->overlay ?? 45) }}" class="range-input">
                </div>
                <button type="submit" class="button-primary mt-7 w-full">Simpan preferensi</button>
            </section>
            <p class="px-2 text-xs leading-5 text-slate-500">Wallpaper dan preferensi dikelola di Mode Managed. Sesi Mandiri tidak menyimpan kredensial router.</p>
        </aside>
    </form>
    <form method="POST" action="{{ route('preferences.sidebar.update') }}" class="glass-panel mt-6 space-y-4 p-5 sm:p-7">
        @csrf
        @method('PUT')
        <div>
            <h2 class="font-semibold text-white">Teks bagian bawah sidebar</h2>
            <p class="mt-1 text-xs text-slate-400">Hanya berlaku untuk akun Anda. Kosongkan kolom untuk menggunakan teks bawaan.</p>
        </div>
        <label class="block"><span class="field-label">Judul sidebar</span><input name="sidebar_title" class="input-glass" maxlength="60" value="{{ old('sidebar_title', $preference->sidebar_title) }}" placeholder="Managed CHR"></label>
        <label class="block"><span class="field-label">Keterangan sidebar</span><textarea name="sidebar_description" class="input-glass" maxlength="240" rows="3" placeholder="Node utama dipilih otomatis; akun VPN dikelola di seluruh cluster.">{{ old('sidebar_description', $preference->sidebar_description) }}</textarea></label>
        <button type="submit" class="button-primary">Simpan teks sidebar</button>
    </form>
@endsection
