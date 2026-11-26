@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@php
    $interfaceLabel = $interfaceType === 'ethernet' ? 'Ethernet' : 'VLAN';
    $workspaceTitle = 'Auto ON/OFF '.$interfaceLabel;
    $generateRoute = $interfaceType === 'ethernet'
        ? route('workspaces.ethernet-scheduler.generate')
        : route('workspaces.vlan-scheduler.generate');
@endphp

@section('title', $workspaceTitle)
@section('breadcrumb', 'Workspaces / '.$workspaceTitle)
@section('mobile-heading', $workspaceTitle)

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
        <div class="mb-7 mt-5">
            <span class="preview-pill preview-pill-active">Aktif · pembuat script</span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">{{ $workspaceTitle }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Jadwalkan interface {{ $interfaceLabel }} untuk ON dan OFF otomatis dengan scheduler RouterOS v7. Pilih pengulangan setiap hari atau sekali pada tanggal yang ditentukan.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <section class="glass-panel p-5 sm:p-7">
                    <div class="mb-6">
                        <h2 class="font-semibold text-white">Jadwal interface</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-400">Contoh nama: {{ $interfaceType === 'ethernet' ? 'ether3' : 'vlan100' }}. Empat kolom waktu memisahkan jam dan menit.</p>
                    </div>
                    <form method="POST" action="{{ $generateRoute }}" x-data="{ scheduleMode: @js(old('schedule_mode', 'daily')) }" class="space-y-5">
                        @csrf
                        <div>
                            <label for="interface_name" class="field-label">Nama interface {{ $interfaceLabel }}</label>
                            <input id="interface_name" name="interface_name" type="text" required maxlength="64" pattern="[A-Za-z0-9._-]+" autocomplete="off" value="{{ old('interface_name') }}" class="input-glass" placeholder="{{ $interfaceType === 'ethernet' ? 'ether3' : 'vlan100' }}">
                            @error('interface_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="schedule_mode" class="field-label">Jenis jadwal</label>
                            <select id="schedule_mode" name="schedule_mode" x-model="scheduleMode" class="input-glass">
                                <option value="daily">Setiap hari</option>
                                <option value="once">Sekali pada tanggal tertentu</option>
                            </select>
                            @error('schedule_mode')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div x-cloak x-show="scheduleMode === 'once'" class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="on_date" class="field-label">Tanggal ON</label>
                                <input id="on_date" name="on_date" type="date" value="{{ old('on_date') }}" :required="scheduleMode === 'once'" :disabled="scheduleMode !== 'once'" class="input-glass">
                                @error('on_date')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="off_date" class="field-label">Tanggal OFF</label>
                                <input id="off_date" name="off_date" type="date" value="{{ old('off_date') }}" :required="scheduleMode === 'once'" :disabled="scheduleMode !== 'once'" class="input-glass">
                                @error('off_date')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div>
                            <p class="field-label">Waktu ON dan OFF</p>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <label for="on_hour" class="field-hint">Jam ON</label>
                                    <input id="on_hour" name="on_hour" type="number" min="0" max="23" required value="{{ old('on_hour') }}" class="input-glass" placeholder="18">
                                    @error('on_hour')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="on_minute" class="field-hint">Menit ON</label>
                                    <input id="on_minute" name="on_minute" type="number" min="0" max="59" required value="{{ old('on_minute') }}" class="input-glass" placeholder="30">
                                    @error('on_minute')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="off_hour" class="field-hint">Jam OFF</label>
                                    <input id="off_hour" name="off_hour" type="number" min="0" max="23" required value="{{ old('off_hour') }}" class="input-glass" placeholder="21">
                                    @error('off_hour')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="off_minute" class="field-hint">Menit OFF</label>
                                    <input id="off_minute" name="off_minute" type="number" min="0" max="59" required value="{{ old('off_minute') }}" class="input-glass" placeholder="55">
                                    @error('off_minute')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="button-primary">Buat script jadwal <span aria-hidden="true">→</span></button>
                    </form>
                </section>

                @if($generatedScript)
                    <section class="glass-panel p-5 sm:p-7" aria-labelledby="generated-script-heading">
                        <div class="mb-4">
                            <h2 id="generated-script-heading" class="font-semibold text-white">Script RouterOS v7</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-400">Salin script ini dan jalankan sendiri di terminal MikroTik. Workspace tidak menerapkan konfigurasi ke router.</p>
                        </div>
                        <textarea id="generated-interface-schedule-script" rows="12" readonly spellcheck="false" class="input-glass min-h-72 font-mono text-xs leading-5">{{ $generatedScript }}</textarea>
                    </section>
                @endif
            </div>

            <aside class="space-y-4">
                <section class="glass-panel p-5">
                    <h2 class="font-semibold text-white">Cara kerja</h2>
                    <ul class="mt-4 space-y-3 text-xs leading-5 text-slate-400">
                        <li class="flex gap-2"><span class="text-cyan-200">01</span><span>Scheduler ON mengaktifkan interface; scheduler OFF menonaktifkannya.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">02</span><span>Jadwal harian menggunakan interval 1 hari. Jadwal satu kali menggunakan tanggal ON dan OFF yang dipilih.</span></li>
                        <li class="flex gap-2"><span class="text-cyan-200">03</span><span>Script hanya mengganti dua scheduler bernama khusus untuk interface ini.</span></li>
                    </ul>
                </section>
                <p class="rounded-xl border border-amber-200/15 bg-amber-200/[.05] p-4 text-xs leading-5 text-amber-100/80">Pastikan nama interface tepat dan tanggal, jam, serta zona waktu router sudah benar. Menonaktifkan interface dapat memutus koneksi yang melewatinya. Periksa script sebelum menjalankannya; halaman ini hanya membuat preview.</p>
            </aside>
        </div>
    </div>
@endsection
