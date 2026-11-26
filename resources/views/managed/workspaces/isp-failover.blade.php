@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Auto Failover Route ISP')
@section('breadcrumb', 'Workspaces / Auto Failover Route ISP')
@section('mobile-heading', 'Auto Failover ISP')

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
        <div class="mb-7 mt-5">
            <span class="preview-pill preview-pill-active">Aktif · pembuat script</span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">Auto Failover Route ISP</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Buat script failover recursive untuk MikroTik RouterOS v7. ISP 1 menjadi jalur utama, ISP 2 mengambil alih otomatis saat probe ISP 1 tidak terjangkau, lalu ISP 1 kembali diprioritaskan saat pulih.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <section class="glass-panel p-5 sm:p-7">
                    <div class="mb-6">
                        <h2 class="font-semibold text-white">Gateway WAN</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-400">Masukkan alamat IPv4 gateway yang dipakai masing-masing jalur ISP.</p>
                    </div>
                    <form method="POST" action="{{ route('workspaces.isp-failover.generate') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label for="gateway_isp1" class="field-label">Gateway ISP 1 · utama</label>
                            <input id="gateway_isp1" name="gateway_isp1" type="text" inputmode="decimal" autocomplete="off" required value="{{ old('gateway_isp1') }}" class="input-glass" placeholder="Contoh: 192.168.1.1">
                            @error('gateway_isp1')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="gateway_isp2" class="field-label">Gateway ISP 2 · cadangan</label>
                            <input id="gateway_isp2" name="gateway_isp2" type="text" inputmode="decimal" autocomplete="off" required value="{{ old('gateway_isp2') }}" class="input-glass" placeholder="Contoh: 192.168.2.1">
                            @error('gateway_isp2')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="button-primary">Buat script failover <span aria-hidden="true">→</span></button>
                    </form>
                </section>

                @if($generatedScript)
                    <section class="glass-panel p-5 sm:p-7" aria-labelledby="generated-script-heading">
                        <div class="mb-4">
                            <h2 id="generated-script-heading" class="font-semibold text-white">Script RouterOS v7</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-400">Salin script ini dan jalankan sendiri di terminal MikroTik. Workspace tidak menerapkan konfigurasi ke router.</p>
                        </div>
                        <textarea id="generated-isp-failover-script" rows="20" readonly spellcheck="false" class="input-glass min-h-96 font-mono text-xs leading-5">{{ $generatedScript }}</textarea>
                    </section>
                @endif
            </div>

            <aside class="space-y-4">
                <section class="glass-panel p-5">
                    <h2 class="font-semibold text-white">Cara kerja</h2>
                    <ul class="mt-4 space-y-3 text-xs leading-5 text-slate-400">
                        <li class="flex gap-2"><span class="text-cyan-200">01</span><span>Dua target probe independen diarahkan melalui masing-masing gateway untuk memeriksa konektivitas internet.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">02</span><span>Route ISP 1 memakai distance 1; ISP 2 distance 2. Jika probe ISP 1 pulih, route utama aktif kembali otomatis.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">03</span><span>Dua target probe per ISP mengurangi failover palsu saat satu host probe tidak menjawab.</span></li>
                    </ul>
                </section>
                <p class="rounded-xl border border-amber-200/15 bg-amber-200/[.05] p-4 text-xs leading-5 text-amber-100/80">Pastikan gateway benar, target probe dapat dijangkau melalui ISP masing-masing, dan ICMP tidak diblokir. Jika WAN memakai DHCP/PPPoE, atur client agar tidak menambahkan default route lain. Tinjau route yang sudah ada; script hanya memperbarui route dengan comment khusus VPN Management.</p>
            </aside>
        </div>
    </div>
@endsection
