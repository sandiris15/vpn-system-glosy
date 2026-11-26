@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Template Hotspot')
@section('breadcrumb', 'Workspaces / Template Hotspot')
@section('mobile-heading', 'Template Hotspot')

@section('content')
    <div x-data="hotspotPreview()">
        <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
        <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">Template halaman login Hotspot</h1>
        <p class="mt-2 text-sm leading-6 text-slate-400">Atur tampilan, lihat preview, lalu unduh ZIP template untuk dipasang secara manual di Hotspot MikroTik.</p>
        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <section class="glass-panel space-y-5 p-5">
                <h2 class="text-lg font-semibold">Pengaturan preview</h2>
                <fieldset>
                    <legend class="field-label">Model template</legend>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="button-secondary" :class="model === 'clean' ? 'ring-2 ring-sky-400' : ''" :aria-pressed="model === 'clean'" @click="model = 'clean'">Clean Connect</button>
                        <button type="button" class="button-secondary" :class="model === 'ads' ? 'ring-2 ring-sky-400' : ''" :aria-pressed="model === 'ads'" @click="model = 'ads'">Clean Connect + Iklan</button>
                    </div>
                </fieldset>
                <label class="block"><span class="field-label">Nama usaha</span><input class="input-glass" x-model="brand" maxlength="60" placeholder="Nama usaha"></label>
                <details class="rounded-xl border border-white/10 bg-white/[.03] p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-white">Edit teks halaman dan popup</summary>
                    <div class="mt-4 space-y-4">
                        <label class="block"><span class="field-label">Teks di bawah nama usaha</span><input class="input-glass" x-model="brandCaption" maxlength="80"></label>
                        <label class="block"><span class="field-label">Judul sambutan halaman</span><input class="input-glass" x-model="welcomeTitle" maxlength="80"></label>
                        <label class="block"><span class="field-label">Judul popup promo</span><input class="input-glass" x-model="promoTitle" maxlength="80"></label>
                        <label class="block"><span class="field-label">Keterangan popup promo</span><textarea class="input-glass" x-model="promoDescription" maxlength="160" rows="2"></textarea></label>
                        <label class="block"><span class="field-label">Teks tombol popup</span><input class="input-glass" x-model="promoButton" maxlength="60"></label>
                        <label class="block"><span class="field-label">Teks bagian bawah halaman</span><textarea class="input-glass" x-model="footerText" maxlength="160" rows="2"></textarea></label>
                    </div>
                </details>
                <label class="block"><span class="field-label">Logo (PNG transparan, JPG, atau WebP, maksimal 2 MB)</span><input x-ref="logoInput" type="file" class="file-input" accept="image/png,image/jpeg,image/webp" @change="setLogo($event)"></label>
                <p class="text-sm text-rose-200" role="alert" x-text="logoError"></p>
                <button x-show="logo" x-cloak type="button" class="text-sm text-rose-200 hover:text-white" @click="clearLogo()">Hapus logo</button>
                <div>
                    <label class="block"><span class="field-label">Gambar popup promo (PNG atau JPG, maksimal 2 MB)</span><input x-ref="promoInput" type="file" class="file-input" accept="image/png,image/jpeg" @change="setPromo($event)"></label>
                    <p class="mt-2 text-xs text-slate-400">Gambar akan muncul sebelum halaman login. Jika belum ada gambar, preview menampilkan placeholder.</p>
                    <p class="mt-2 text-sm text-rose-200" role="alert" x-text="promoError"></p>
                    <button x-show="promo" x-cloak type="button" class="mt-2 text-sm text-rose-200 hover:text-white" @click="clearPromo()">Hapus gambar promo</button>
                </div>
                <div x-show="model === 'ads'" x-cloak>
                    <label class="block"><span class="field-label">Gambar iklan bawah (PNG, JPG, atau WebP, maksimal 2 MB per gambar)</span><input x-ref="adsInput" type="file" class="file-input" accept="image/png,image/jpeg,image/webp" multiple @change="setAds($event)"></label>
                    <p class="mt-2 text-xs text-slate-400">Pilih beberapa gambar sekaligus; pilih ulang untuk mengganti semuanya.</p>
                    <p class="mt-2 text-xs text-slate-400" role="status" x-show="adsLoading" x-cloak>Memeriksa gambar iklan...</p>
                    <p class="mt-2 text-sm text-rose-200" role="alert" x-text="adsError"></p>
                    <button x-show="ads.length || adsError" x-cloak type="button" class="mt-2 text-sm text-rose-200 hover:text-white" @click="clearAds()">Hapus gambar iklan</button>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="field-label">Warna latar 1<input class="mt-2 block h-11 w-full cursor-pointer rounded-lg border-0" type="color" x-model="background"></label>
                    <label class="field-label">Warna latar 2<input class="mt-2 block h-11 w-full cursor-pointer rounded-lg border-0" type="color" x-model="background2"></label>
                    <label class="field-label">Warna tombol<input class="mt-2 block h-11 w-full cursor-pointer rounded-lg border-0" type="color" x-model="accent"></label>
                </div>
                <section class="space-y-3 rounded-xl border border-white/10 bg-white/[.03] p-4" aria-labelledby="packages-title">
                    <div><h3 id="packages-title" class="text-sm font-semibold text-white">Daftar paket dan harga</h3><p class="mt-1 text-xs text-slate-400">Opsional; daftar ini hanya informasi dan tidak membuat paket di router.</p></div>
                    <template x-for="(item, index) in packages" :key="index">
                        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                            <label><span class="field-label">Nama / durasi paket</span><input class="input-glass" x-model="item.name" maxlength="80" placeholder="Contoh: 1 hari"></label>
                            <label><span class="field-label">Harga</span><input class="input-glass" x-model="item.price" maxlength="60" placeholder="Contoh: Rp 5.000"></label>
                            <button type="button" class="button-secondary self-end" @click="packages.splice(index, 1)" :aria-label="`Hapus paket ${index + 1}`">Hapus</button>
                        </div>
                    </template>
                    <button type="button" class="button-secondary" @click="addPackage()">+ Tambah paket</button>
                </section>
                <p class="text-xs text-slate-400">Pemilih warna mendukung perpaduan warna RGB. Logo dan gambar promo diproses di browser dan menyesuaikan ukuran tanpa terpotong.</p>
            </section>
            <section class="glass-panel p-5">
                <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-semibold">Preview langsung</h2><span class="preview-pill preview-pill-muted">Tidak terhubung router</span></div>
                <div class="mt-4 flex justify-center rounded-2xl bg-slate-950/30 p-3 sm:p-6">
                    <div class="relative w-full max-w-sm overflow-hidden rounded-3xl bg-slate-100 shadow-xl">
                        <div :inert="showPromo">
                        <div class="px-6 pb-12 pt-4 text-center text-white" :style="`background: linear-gradient(135deg, ${background}, ${background2})`">
                            <div class="flex flex-col items-center gap-3 text-center">
                                <img x-show="logo" :src="logo || null" alt="Logo usaha" class="h-28 w-64 max-w-full rounded-xl object-contain" x-on:error="logoLoadError()" x-cloak>
                                <div x-show="!logo" class="grid h-28 w-64 max-w-full place-items-center rounded-xl bg-white/15 text-sm font-bold">WiFi</div>
                                <div class="min-w-0">
                                    <h3 class="break-words text-lg font-bold" x-text="brand || 'Hotspot WiFi'"></h3>
                                    <p class="break-words text-xs text-white/80" x-text="brandCaption"></p>
                                </div>
                            </div>
                            <p class="mt-8 break-words text-2xl font-bold" x-text="welcomeTitle"></p>
                        </div>
                        <div class="relative -mt-6 rounded-3xl bg-white p-5 text-slate-900 shadow-lg">
                            <h4 class="text-center text-base font-bold">Login Internet</h4>
                            <div class="mt-4 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1" aria-label="Metode login preview">
                                <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="mode === 'voucher' ? 'text-white' : 'text-slate-600'" :style="mode === 'voucher' ? `background-color: ${accent}` : ''" :aria-pressed="mode === 'voucher'" @click="selectMode('voucher')">Voucher</button>
                                <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="mode === 'separate' ? 'text-white' : 'text-slate-600'" :style="mode === 'separate' ? `background-color: ${accent}` : ''" :aria-pressed="mode === 'separate'" @click="selectMode('separate')">Member</button>
                            </div>
                            <div class="mt-4 space-y-3 text-left">
                                <label class="block text-xs font-medium text-slate-600"><span x-text="mode === 'voucher' ? 'Kode voucher' : 'Username'"></span><input type="text" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900" :placeholder="mode === 'voucher' ? 'Masukkan kode voucher' : 'Masukkan username'"></label>
                                <label x-show="mode === 'separate'" x-cloak class="block text-xs font-medium text-slate-600">Password<input type="password" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900" placeholder="Masukkan password"></label>
                                <p x-show="mode === 'voucher'" class="text-center text-xs text-slate-500">Kode voucher digunakan sebagai username dan password.</p>
                                <button type="button" class="w-full rounded-lg px-4 py-3 text-sm font-bold text-white" :style="`background-color: ${accent}`" @click="previewMessage = 'Ini hanya preview, tidak melakukan login.'">Masuk Hotspot</button>
                                <p class="text-center text-xs text-slate-500" role="status" x-text="previewMessage"></p>
                            </div>
                        </div>
                        <section x-show="packages.length" x-cloak class="mx-4 mt-4 rounded-2xl bg-white p-5 text-slate-900">
                            <h4 class="text-center text-sm font-bold">Daftar paket</h4>
                            <template x-for="(item, index) in packages" :key="index">
                                <div class="flex justify-between gap-3 border-b border-slate-100 py-2 text-sm">
                                    <span class="break-words" x-text="item.name || 'Nama paket'"></span>
                                    <strong class="break-words text-right" x-text="item.price || 'Harga'"></strong>
                                </div>
                            </template>
                        </section>
                        <section x-show="model === 'ads'" x-cloak class="mx-4 mt-4 rounded-2xl bg-white p-5 text-slate-900" aria-label="Iklan preview">
                            <h4 class="text-center text-sm font-bold">Iklan</h4>
                            <p x-show="!ads.length" class="mt-3 text-center text-xs text-slate-500">Tambahkan gambar iklan di pengaturan.</p>
                            <div x-ref="adSlides" x-show="ads.length" tabindex="0" aria-label="Gambar iklan preview" class="mt-3 flex snap-x snap-mandatory overflow-x-auto rounded-xl">
                                <template x-for="(ad, index) in ads" :key="ad.preview">
                                    <img :src="ad.preview" :alt="`Iklan ${index + 1}`" class="h-40 w-full min-w-full snap-start bg-slate-100 object-contain">
                                </template>
                            </div>
                            <div x-show="ads.length > 1" class="mt-3 flex justify-center gap-2">
                                <button type="button" class="rounded-lg px-4 py-2 text-white" :style="`background-color: ${accent}`" aria-label="Iklan sebelumnya" @click="$refs.adSlides.scrollBy({left: -$refs.adSlides.clientWidth, behavior: 'smooth'})">←</button>
                                <button type="button" class="rounded-lg px-4 py-2 text-white" :style="`background-color: ${accent}`" aria-label="Iklan berikutnya" @click="$refs.adSlides.scrollBy({left: $refs.adSlides.clientWidth, behavior: 'smooth'})">→</button>
                            </div>
                        </section>
                        <div class="whitespace-pre-line break-words p-5 text-center text-xs text-slate-500" x-text="footerText"></div>
                        </div>
                        <div x-show="showPromo" x-cloak class="absolute inset-0 flex items-start justify-center overflow-y-auto bg-slate-950/80 p-5" role="group" aria-label="Popup promo preview" @keydown.escape="showPromo = false">
                            <div class="my-auto w-full rounded-2xl bg-slate-900 p-4 text-center text-white shadow-2xl">
                                <img x-show="promo" :src="promo || null" alt="Gambar promo" class="mx-auto max-h-64 w-full rounded-xl object-contain" x-on:error="promoLoadError()" x-cloak>
                                <div x-show="!promo" class="grid min-h-40 place-items-center rounded-xl border border-dashed border-white/30 bg-white/5 p-4 text-sm text-slate-300">Tambahkan gambar promo PNG atau JPG di pengaturan.</div>
                                <h4 x-show="promoTitle" x-cloak class="mt-4 break-words text-lg font-bold" x-text="promoTitle"></h4>
                                <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-300" x-text="promoDescription"></p>
                                <button type="button" class="mt-4 w-full break-words rounded-xl px-4 py-3 text-sm font-bold text-white" :style="`background: linear-gradient(110deg, ${accent}, ${background2})`" @click="showPromo = false"><span x-text="promoButton"></span> →</button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="button-secondary mt-4" @click="showPromo = true">Tampilkan popup promo lagi</button>
                <button type="button" class="button-primary mt-4" @click="downloadTemplate()" :disabled="exporting" x-text="exporting ? 'Menyiapkan ZIP...' : 'Unduh template ZIP'"></button>
                <p class="mt-2 text-sm text-rose-200" role="alert" x-text="exportError"></p>
                <p class="mt-3 text-xs leading-5 text-slate-400">ZIP berisi folder Hotspot, halaman login/status/logout, CSS, JavaScript, gambar yang dipilih, dan petunjuk pemasangan. Aplikasi tidak memasangnya ke router.</p>
            </section>
        </div>
    </div>
@endsection
