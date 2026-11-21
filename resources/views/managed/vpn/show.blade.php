@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'accounts'])

@section('title', data_get($account, 'is_managed') ? 'VPN berhasil dibuat' : 'PPP Secret')
@section('breadcrumb', 'Akun VPN / Detail')
@section('mobile-heading', 'Detail akun VPN')

@section('content')
    <div class="mx-auto max-w-7xl">
        <a href="/vpn-accounts" class="text-sm text-slate-400 hover:text-white">← Kembali ke akun VPN</a>

        <section class="glass-panel mt-5 p-5 sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <span class="status-pill {{ data_get($account, 'is_managed') ? 'status-online' : 'status-offline' }}"><span class="status-dot"></span>{{ data_get($account, 'is_managed') ? 'Akun dikelola aplikasi' : 'PPP Secret eksternal' }}</span>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-white">{{ data_get($account, 'is_managed') ? 'Akun VPN siap digunakan' : 'Detail PPP Secret SSTP' }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        @if(! data_get($account, 'is_managed'))
                            Akun ini dibuat di luar aplikasi. Script dapat dibuat, tetapi akun tidak dapat diubah atau dihapus melalui aplikasi.
                        @elseif($primaryOnly ?? true)
                            Konfigurasi dibuat pada CHR utama. Remote address dipasang statis pada PPP Secret.
                        @else
                            Konfigurasi dibuat pada {{ $clusterServerCount ?? 1 }} node dengan IP tunnel yang sama.
                        @endif
                    </p>
                </div>
                <a href="/vpn-accounts" class="button-secondary shrink-0">Daftar akun</a>
            </div>

            <dl class="mt-7 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4"><dt class="text-xs text-slate-500">Username VPN</dt><dd class="mt-2 font-medium text-white">{{ data_get($account, 'username', '—') }}</dd></div>
                <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4"><dt class="text-xs text-slate-500">CHR utama</dt><dd class="mt-2 font-medium text-white">{{ data_get($selectedServer, 'name', '—') }}</dd></div>
                <div class="rounded-2xl border border-white/10 bg-white/[.035] p-4"><dt class="text-xs text-slate-500">Remote address</dt><dd class="mt-2 font-mono text-sm text-white">{{ data_get($account, 'remote_address', '—') }}</dd></div>
            </dl>
        </section>

        @php
            $serverEndpoints = collect($managedServers ?? [$selectedServer])
                ->map(fn ($server): array => [
                    'name' => data_get($server, 'name', 'CHR'),
                    'public_ip' => data_get($server, 'public_ip', '—'),
                ])
                ->values();
            $natRules = collect(data_get($account, 'nat_rules', []));
            $portCopyText = $natRules->flatMap(function (array $nat) use ($serverEndpoints, $account): array {
                $protocol = strtoupper((string) data_get($nat, 'protocol', 'tcp'));
                $publicPort = data_get($nat, 'public_port', '—');
                $internalPort = data_get($nat, 'internal_port', '—');

                return $serverEndpoints
                    ->map(fn (array $server): string => $protocol.'/'.$publicPort.' -> '.$server['public_ip'].':'.$publicPort.' ('.data_get($account, 'remote_address', '—').':'.$internalPort.')')
                    ->all();
            })->implode("\n");
            $publicIpLines = $serverEndpoints
                ->map(fn (array $server): string => '- '.$server['name'].': '.$server['public_ip'])
                ->implode("\n");
            $natLines = $natRules->flatMap(function (array $nat) use ($serverEndpoints): array {
                $publicPort = data_get($nat, 'public_port', '');
                $internalPort = (string) data_get($nat, 'internal_port', '');

                $label = match ($internalPort) {
                    '80' => 'Port WWW (Web)',
                    '8291' => 'Port Winbox',
                    '8728' => 'Port MikroTik API',
                    default => 'Port '.$internalPort,
                };

                if ($publicPort === '') {
                    return [];
                }

                $lines = [
                    '- '.$label,
                    '  Protokol: '.strtoupper((string) data_get($nat, 'protocol', 'tcp')),
                ];

                foreach ($serverEndpoints as $server) {
                    $lines[] = '  '.$server['name'].': '.$server['public_ip'].':'.$publicPort;
                }

                $comment = trim(str_replace(["\r", "\n"], ' ', (string) data_get($nat, 'comment', '')));
                $lines[] = '  Comment: '.($comment !== '' ? $comment : '—');

                return [...$lines, ''];
            })->implode("\n");

            $copyData = "Halo, berikut adalah informasi akses VPN & Port Forwarding Anda VIA DATA CENTER PT NAT:\n\n"
                .'👤 Username: '.data_get($account, 'username', '—')."\n"
                ."🌐 IP Publik Server:\n".$publicIpLines;

            if ($natLines !== '') {
                $copyData .= "\n\n🔌 Masukan IP + Port:\n".rtrim($natLines);
            }

            $copyData .= "\n\nSilakan disimpan dengan baik. Terima kasih!";
        @endphp

        @if(session()->has('status'))
            <div
                x-data="{ open: true, copyState: 'idle', async copyPort() { try { await navigator.clipboard.writeText(@js($portCopyText)); this.copyState = 'copied'; } catch (error) { this.copyState = 'error'; } } }"
                x-show="open"
                x-cloak
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                aria-labelledby="vpn-created-title"
                @keydown.escape.window="open = false"
            >
                <section class="glass-panel w-full max-w-lg p-6 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="status-pill status-online"><span class="status-dot"></span>Provisioning berhasil</span>
                            <h2 id="vpn-created-title" class="mt-4 text-xl font-semibold text-white">Akun VPN siap digunakan</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-400">Pilih tindakan berikutnya untuk akun {{ data_get($account, 'username', '—') }}.</p>
                        </div>
                        <button type="button" class="button-table" aria-label="Tutup popup" @click="open = false">Tutup</button>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-3">
                        <button type="button" class="button-secondary" @click="copyPort()" @disabled($portCopyText === '')>
                            <span x-text="copyState === 'copied' ? 'Port tersalin ✓' : (copyState === 'error' ? 'Gagal menyalin' : 'Salin port')">Salin port</span>
                        </button>
                        <button type="submit" form="generate-vpn-script" name="script_mode" value="vpn" class="button-secondary">Generate VPN saja</button>
                        <button type="submit" form="generate-vpn-script" name="script_mode" value="speedtest" class="button-primary">Generate VPN + Speedtest</button>
                    </div>
                    @if($portCopyText === '')
                        <p class="mt-3 text-xs text-slate-500">Akun ini tidak memiliki aturan DST-NAT untuk disalin.</p>
                    @endif
                    <p x-cloak x-show="copyState === 'error'" class="mt-3 text-xs text-rose-200" role="alert">Clipboard gagal; salin port dari informasi akses secara manual.</p>
                </section>
            </div>
        @endif

        <section class="glass-panel mt-5 p-5 sm:p-8" x-data="{ copyState: 'idle', async copyData() { try { await navigator.clipboard.writeText(@js($copyData)); this.copyState = 'copied'; } catch (error) { this.copyState = 'error'; } } }">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                <div>
                    <h2 class="font-semibold text-white">Informasi akses untuk pelanggan</h2>
                    <p class="mt-1 text-xs text-slate-400">Format ringkas berisi username dan semua port forwarding akun ini.</p>
                </div>
                <button type="button" class="button-secondary shrink-0" @click="copyData()">
                    <span x-text="copyState === 'copied' ? 'Data tersalin ✓' : (copyState === 'error' ? 'Gagal menyalin' : 'Salin data')">Salin data</span>
                </button>
            </div>
            <pre class="mt-5 whitespace-pre-wrap rounded-2xl border border-white/10 bg-slate-950/65 p-4 text-sm leading-6 text-slate-200">{{ $copyData }}</pre>
            <p x-cloak x-show="copyState === 'error'" class="mt-2 text-xs text-rose-200" role="alert">Clipboard tidak tersedia. Salin data secara manual.</p>
        </section>

        <section class="glass-panel mt-5 p-5 sm:p-8" x-data="{ copyState: 'idle', async copyScript() { try { await navigator.clipboard.writeText(document.getElementById('generated-script').textContent); this.copyState = 'copied'; } catch (error) { this.copyState = 'error'; } } }">
            <div class="flex items-start gap-4">
                <span class="step-number">S</span>
                <div>
                    <h2 class="font-semibold text-white">Script Generator</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-400">
                        @if($primaryOnly ?? true)
                            Pilih script SSTP VPN saja atau SSTP VPN dengan routing khusus tujuan Speedtest.
                        @else
                            Pilih script SSTP VPN dengan failover cluster, dengan atau tanpa routing khusus tujuan Speedtest.
                        @endif
                    </p>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-amber-200/10 bg-amber-200/[.05] p-4 text-xs leading-5 text-amber-100/80">
                VPN saja hanya membuat SSTP client dan tidak menambahkan routing Speedtest. Mode Speedtest juga menambahkan address-list, mangle, route, dan NAT. Validasi sertifikat SSTP dinonaktifkan sesuai konfigurasi yang diminta; gunakan hanya pada endpoint tepercaya.
            </div>

            <form id="generate-vpn-script" method="POST" action="/vpn-accounts/{{ data_get($account, 'id') }}/script" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                @csrf
                <p class="max-w-3xl text-xs leading-5 text-slate-500">Pastikan CHR meneruskan trafik VPN ke internet dan memiliki source NAT yang sesuai. Mode Speedtest menambahkan daftar tujuan hanya jika belum ada.</p>
                <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                    <button type="submit" name="script_mode" value="vpn" class="button-secondary">Generate VPN saja</button>
                    <button type="submit" name="script_mode" value="speedtest" class="button-primary">Generate VPN + Speedtest</button>
                </div>
            </form>

            @if(isset($generatedScript) && $generatedScript !== '')
                <div class="mt-7 border-t border-white/10 pt-6">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div><h3 class="font-semibold text-white">Preview script RouterOS v7 — {{ ($scriptMode ?? 'speedtest') === 'vpn' ? 'VPN saja' : 'VPN + Speedtest' }}</h3><p class="mt-1 text-xs text-slate-400">Preview melebar dan dapat diubah ukurannya agar mudah diperiksa.</p></div>
                        <button type="button" class="button-secondary shrink-0" @click="copyScript()"><span x-text="copyState === 'copied' ? 'Tersalin ✓' : (copyState === 'error' ? 'Gagal menyalin' : 'Salin script')">Salin script</span></button>
                    </div>
                    <pre class="mt-4 min-h-[22rem] max-h-[75vh] w-full max-w-full resize overflow-auto whitespace-pre rounded-2xl border border-white/10 bg-slate-950/65 p-4 text-xs leading-6 text-rose-100"><code id="generated-script" class="block min-w-max">{{ $generatedScript }}</code></pre>
                    <p x-cloak x-show="copyState === 'error'" class="mt-2 text-xs text-rose-200" role="alert">Clipboard tidak tersedia. Salin script secara manual.</p>
                    <p class="mt-3 text-xs leading-5 text-amber-100/70">Script berisi password VPN. Jangan bagikan dan jangan simpan pada file atau URL publik.</p>
                </div>
            @endif
        </section>
    </div>
@endsection
