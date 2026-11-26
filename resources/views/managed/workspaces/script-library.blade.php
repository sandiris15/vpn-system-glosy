@extends('layouts.app', ['mode' => 'managed', 'activePage' => 'workspaces'])

@section('title', 'Kumpulan Script Umum')
@section('breadcrumb', 'Workspaces / Kumpulan Script Umum')
@section('mobile-heading', 'Kumpulan Script')

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('workspaces.index') }}" class="text-sm text-slate-400 hover:text-white">← Semua workspace</a>
        <div class="mb-7 mt-5">
            <span class="preview-pill preview-pill-active">Aktif · pembuat script</span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">Kumpulan Script Umum</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Pilih script yang sering digunakan. Workspace menyiapkan teks script untuk ditinjau dan dijalankan manual di MikroTik.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="glass-panel p-5 sm:p-7">
                <div class="mb-5">
                    <h2 class="font-semibold text-white">Kosongkan alamat PPP Secret</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">Menghapus local-address dan remote-address dari semua PPP Secret. Script ini tidak membutuhkan input selain konfirmasi.</p>
                </div>
                <form method="POST" action="{{ route('workspaces.script-library.generate') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="script_type" value="clear-ppp-addresses">
                    <label class="flex items-start gap-3 rounded-xl border border-amber-200/15 bg-amber-200/[.05] p-3 text-xs leading-5 text-amber-100/90">
                        <input type="checkbox" name="confirm_clear" value="1" required class="mt-0.5 h-4 w-4 rounded border-white/20 bg-slate-900 text-cyan-300 focus:ring-cyan-300/40">
                        Saya paham script ini mengubah seluruh PPP Secret pada router.
                    </label>
                    @error('confirm_clear')<p class="field-error">{{ $message }}</p>@enderror
                    <button type="submit" class="button-primary">Buat script kosongkan alamat</button>
                </form>
            </section>

            <section class="glass-panel p-5 sm:p-7">
                <div class="mb-5">
                    <h2 class="font-semibold text-white">Backup PPP Secret</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">Ekspor daftar PPP Secret ke file RouterOS, termasuk nilai sensitif seperti password.</p>
                </div>
                <form method="POST" action="{{ route('workspaces.script-library.generate') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="script_type" value="backup-ppp-secrets">
                    <div>
                        <label for="backup_file" class="field-label">Nama file backup</label>
                        <input id="backup_file" name="backup_file" type="text" required maxlength="64" pattern="[A-Za-z0-9_-]+" autocomplete="off" value="{{ old('backup_file', 'BackupPPPoE') }}" class="input-glass" placeholder="BackupPPPoE">
                        <p class="field-hint">Gunakan huruf, angka, garis bawah, atau tanda hubung. RouterOS menambahkan ekstensi file export.</p>
                        @error('backup_file')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="button-primary">Buat script backup</button>
                </form>
            </section>
        </div>

        @if($generatedScript)
            <section class="glass-panel mt-6 p-5 sm:p-7" aria-labelledby="generated-library-script-heading">
                <div class="mb-4">
                    <h2 id="generated-library-script-heading" class="font-semibold text-white">{{ $generatedScriptLabel }}</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-400">Tinjau script sebelum menjalankannya. Tidak ada perubahan yang dikirim dari workspace ini ke router.</p>
                </div>
                @if($generatedScriptLabel === 'Backup PPP Secret dengan password')
                    <p class="mb-4 rounded-xl border border-amber-200/15 bg-amber-200/[.05] p-3 text-xs leading-5 text-amber-100/90">File export berisi password PPP. Batasi akses ke file tersebut dan hapus setelah backup dipindahkan ke penyimpanan aman.</p>
                @endif
                <textarea id="generated-library-script" rows="8" readonly spellcheck="false" class="input-glass min-h-40 font-mono text-xs leading-5">{{ $generatedScript }}</textarea>
            </section>
        @endif
    </div>
@endsection
