@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Setup MikroTik Baru')
@section('breadcrumb', 'Workspaces / Setup MikroTik Baru')
@section('mobile-heading', 'Setup MikroTik')

@section('content')
    @php($lanMode = old('lan_mode', $setupInput['lan_mode'] ?? 'ethernet'))
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
        <div class="mb-7 mt-5">
            <span class="preview-pill preview-pill-active">Aktif · pembuat script</span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">Setup MikroTik Baru</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Siapkan script RouterOS v7 untuk WAN DHCP, interface LAN atau VLAN, NAT, PPPoE, Hotspot, DNS, identitas, admin baru, dan layanan yang akan dinonaktifkan.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                @error('configuration')
                    <div class="rounded-2xl border border-rose-200/20 bg-rose-200/[.08] p-4 text-sm leading-6 text-rose-100" role="alert">{{ $message }}</div>
                @enderror

                <form method="POST" action="{{ route('workspaces.mikrotik-setup.generate') }}" x-data="{ lanMode: @js($lanMode) }" class="space-y-6">
                    @csrf
                    <section class="glass-panel p-5 sm:p-7">
                        <div class="mb-6">
                            <h2 class="font-semibold text-white">Identitas dan akses</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-400">Script membuat akun admin baru dengan group full sebelum mematikan layanan yang dipilih.</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="identity" class="field-label">Identity router</label>
                                <input id="identity" name="identity" type="text" required maxlength="64" autocomplete="off" value="{{ old('identity', $setupInput['identity'] ?? '') }}" class="input-glass" placeholder="Router-Cabang-01">
                                @error('identity')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="admin_username" class="field-label">Username admin baru</label>
                                <input id="admin_username" name="admin_username" type="text" required maxlength="32" autocomplete="off" value="{{ old('admin_username', $setupInput['admin_username'] ?? '') }}" class="input-glass" placeholder="admin-baru">
                                @error('admin_username')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="admin_password" class="field-label">Password admin baru</label>
                                <input id="admin_password" name="admin_password" type="password" required minlength="12" maxlength="128" autocomplete="new-password" class="input-glass" placeholder="Minimal 12 karakter">
                                <p class="field-hint">Password hanya dipakai untuk membuat preview script, tidak disimpan sebagai input lama.</p>
                                @error('admin_password')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="glass-panel p-5 sm:p-7">
                        <div class="mb-6">
                            <h2 class="font-semibold text-white">WAN dan LAN</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-400">WAN menerima IP otomatis melalui DHCP. Pilih LAN Ethernet langsung atau buat VLAN pada interface induk.</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="wan_interface" class="field-label">Interface WAN · DHCP client</label>
                                <input id="wan_interface" name="wan_interface" type="text" required maxlength="64" pattern="[A-Za-z0-9._-]+" autocomplete="off" value="{{ old('wan_interface', $setupInput['wan_interface'] ?? '') }}" class="input-glass" placeholder="ether1">
                                @error('wan_interface')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="lan_mode" class="field-label">Mode interface LAN</label>
                                <select id="lan_mode" name="lan_mode" x-model="lanMode" class="input-glass">
                                    <option value="ethernet">Interface Ethernet yang sudah ada</option>
                                    <option value="vlan">Buat VLAN baru</option>
                                </select>
                                @error('lan_mode')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div x-show="lanMode === 'ethernet'" x-cloak>
                                <label for="lan_interface" class="field-label">Interface LAN</label>
                                <input id="lan_interface" name="lan_interface" type="text" maxlength="64" pattern="[A-Za-z0-9._-]+" autocomplete="off" value="{{ old('lan_interface', $setupInput['lan_interface'] ?? '') }}" :required="lanMode === 'ethernet'" :disabled="lanMode !== 'ethernet'" class="input-glass" placeholder="ether2">
                                @error('lan_interface')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div x-show="lanMode === 'vlan'" x-cloak>
                                <label for="vlan_parent" class="field-label">Interface induk VLAN</label>
                                <input id="vlan_parent" name="vlan_parent" type="text" maxlength="64" pattern="[A-Za-z0-9._-]+" autocomplete="off" value="{{ old('vlan_parent', $setupInput['vlan_parent'] ?? '') }}" :required="lanMode === 'vlan'" :disabled="lanMode !== 'vlan'" class="input-glass" placeholder="ether2">
                                @error('vlan_parent')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div x-show="lanMode === 'vlan'" x-cloak>
                                <label for="vlan_name" class="field-label">Nama interface VLAN</label>
                                <input id="vlan_name" name="vlan_name" type="text" maxlength="64" pattern="[A-Za-z0-9._-]+" autocomplete="off" value="{{ old('vlan_name', $setupInput['vlan_name'] ?? '') }}" :required="lanMode === 'vlan'" :disabled="lanMode !== 'vlan'" class="input-glass" placeholder="vlan100">
                                @error('vlan_name')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div x-show="lanMode === 'vlan'" x-cloak>
                                <label for="vlan_id" class="field-label">VLAN ID</label>
                                <input id="vlan_id" name="vlan_id" type="number" min="1" max="4094" value="{{ old('vlan_id', $setupInput['vlan_id'] ?? '') }}" :required="lanMode === 'vlan'" :disabled="lanMode !== 'vlan'" class="input-glass" placeholder="100">
                                @error('vlan_id')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="lan_address" class="field-label">IP lokal LAN · IPv4/prefix</label>
                                <input id="lan_address" name="lan_address" type="text" required inputmode="decimal" autocomplete="off" value="{{ old('lan_address', $setupInput['lan_address'] ?? '') }}" class="input-glass" placeholder="192.168.10.1/24">
                                <p class="field-hint">Contoh 192.168.10.1/24. Pool PPPoE dan Hotspot harus berada di subnet ini dan tidak saling tumpang tindih.</p>
                                @error('lan_address')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="glass-panel p-5 sm:p-7">
                        <div class="mb-6">
                            <h2 class="font-semibold text-white">PPPoE dan Hotspot</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-400">Kedua layanan akan memakai interface LAN yang dipilih di atas, dengan pool IP terpisah.</p>
                        </div>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="pppoe_service_name" class="field-label">Nama layanan PPPoE</label>
                                <input id="pppoe_service_name" name="pppoe_service_name" type="text" required maxlength="64" pattern="[A-Za-z0-9._-]+" value="{{ old('pppoe_service_name', $setupInput['pppoe_service_name'] ?? 'pppoe') }}" class="input-glass">
                                @error('pppoe_service_name')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="hotspot_server_name" class="field-label">Nama server Hotspot</label>
                                <input id="hotspot_server_name" name="hotspot_server_name" type="text" required maxlength="64" pattern="[A-Za-z0-9._-]+" value="{{ old('hotspot_server_name', $setupInput['hotspot_server_name'] ?? 'hotspot1') }}" class="input-glass">
                                @error('hotspot_server_name')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="pppoe_pool_start" class="field-label">Awal pool IP PPPoE</label>
                                <input id="pppoe_pool_start" name="pppoe_pool_start" type="text" required inputmode="decimal" value="{{ old('pppoe_pool_start', $setupInput['pppoe_pool_start'] ?? '') }}" class="input-glass" placeholder="192.168.10.2">
                                @error('pppoe_pool_start')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="pppoe_pool_end" class="field-label">Akhir pool IP PPPoE</label>
                                <input id="pppoe_pool_end" name="pppoe_pool_end" type="text" required inputmode="decimal" value="{{ old('pppoe_pool_end', $setupInput['pppoe_pool_end'] ?? '') }}" class="input-glass" placeholder="192.168.10.50">
                                @error('pppoe_pool_end')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="hotspot_pool_start" class="field-label">Awal pool IP Hotspot/DHCP</label>
                                <input id="hotspot_pool_start" name="hotspot_pool_start" type="text" required inputmode="decimal" value="{{ old('hotspot_pool_start', $setupInput['hotspot_pool_start'] ?? '') }}" class="input-glass" placeholder="192.168.10.51">
                                @error('hotspot_pool_start')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="hotspot_pool_end" class="field-label">Akhir pool IP Hotspot/DHCP</label>
                                <input id="hotspot_pool_end" name="hotspot_pool_end" type="text" required inputmode="decimal" value="{{ old('hotspot_pool_end', $setupInput['hotspot_pool_end'] ?? '') }}" class="input-glass" placeholder="192.168.10.200">
                                @error('hotspot_pool_end')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="glass-panel p-5 sm:p-7">
                        <div class="mb-6">
                            <h2 class="font-semibold text-white">DNS dan keamanan</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-400">Pilih DNS upstream. FTP, SSH, dan Telnet akan dinonaktifkan oleh script.</p>
                        </div>
                        <div>
                            <label for="dns_servers" class="field-label">DNS upstream</label>
                            <select id="dns_servers" name="dns_servers" class="input-glass">
                                <option value="both" @selected(old('dns_servers', $setupInput['dns_servers'] ?? 'both') === 'both')>Cloudflare 1.1.1.1 + Google 8.8.8.8</option>
                                <option value="cloudflare" @selected(old('dns_servers', $setupInput['dns_servers'] ?? 'both') === 'cloudflare')>Cloudflare 1.1.1.1</option>
                                <option value="google" @selected(old('dns_servers', $setupInput['dns_servers'] ?? 'both') === 'google')>Google 8.8.8.8</option>
                            </select>
                            @error('dns_servers')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="button-primary mt-6">Buat preview script <span aria-hidden="true">→</span></button>
                    </section>
                </form>

                @if($generatedScript)
                    <section class="glass-panel p-5 sm:p-7" aria-labelledby="generated-setup-script-heading">
                        <div class="mb-4">
                            <h2 id="generated-setup-script-heading" class="font-semibold text-white">Script konfigurasi RouterOS v7</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-400">Preview ini memuat password admin baru. Salin hanya ke router yang berwenang, lalu tutup halaman setelah digunakan.</p>
                        </div>
                        <textarea id="generated-mikrotik-setup-script" rows="28" readonly spellcheck="false" class="input-glass min-h-[42rem] font-mono text-xs leading-5">{{ $generatedScript }}</textarea>
                    </section>
                @endif
            </div>

            <aside class="space-y-4">
                <section class="glass-panel p-5">
                    <h2 class="font-semibold text-white">Urutan script</h2>
                    <ul class="mt-4 space-y-3 text-xs leading-5 text-slate-400">
                        <li class="flex gap-2"><span class="text-cyan-200">01</span><span>Validasi konflik dasar lalu buat akun baru dengan group full.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">02</span><span>Aktifkan DHCP client WAN, buat VLAN bila dipilih, lalu atur IP lokal, NAT, pool, dan server.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">03</span><span>Atur DNS dan nonaktifkan FTP, SSH, serta Telnet setelah akun baru dibuat.</span></li>
                    </ul>
                </section>
                <p class="rounded-xl border border-amber-200/15 bg-amber-200/[.05] p-4 text-xs leading-5 text-amber-100/80">Script tidak dijalankan otomatis. Pastikan pool IP tidak dipakai perangkat lain dan interface WAN/LAN benar. PPPoE serta Hotspot akan memakai interface LAN yang sama; pastikan desain jaringan mendukung keduanya. Uji akun admin baru sebelum menonaktifkan layanan lama. Password hanya ditampilkan pada preview script dan tidak disimpan ke input lama.</p>
            </aside>
        </div>
    </div>
@endsection
