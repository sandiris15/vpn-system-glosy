<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NOC Portal') · RUANG NOC NAT WAIPI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-slate-100 antialiased">
    @php
        $displayPreference = auth()->user()?->preference;
        $displayWallpaper = $displayPreference?->wallpaper_upload_path
            ? route('preferences.wallpaper')
            : match($displayPreference?->wallpaper_id) {
                'rose-dawn' => '/images/wallpapers/rose-dawn.svg',
                'violet-night' => '/images/wallpapers/violet-night.svg',
                'ocean-glow' => '/images/wallpapers/ocean-glow.svg',
                default => null,
            };
    @endphp
    <div
        class="wallpaper fixed inset-0 -z-10"
        @if($displayWallpaper)
            style="background-image: linear-gradient(135deg, rgba(15,23,42,.08), rgba(15,23,42,.48)), url('{{ $displayWallpaper }}'); filter: blur({{ $displayPreference->blur ?? 0 }}px); transform: scale(1.04)"
        @endif
        aria-hidden="true"
    ></div>
    <div
        class="fixed inset-0 -z-10 bg-slate-950"
        style="opacity: {{ ($displayPreference?->overlay ?? 45) / 100 }}"
        aria-hidden="true"
    ></div>

    <div
        x-data="{ sidebarOpen: false, profileOpen: false }"
        class="min-h-screen lg:flex"
    >
        <div
            x-cloak
            x-show="sidebarOpen"
            x-transition.opacity
            class="fixed inset-0 z-30 bg-slate-950/60 backdrop-blur-sm lg:hidden"
            @click="sidebarOpen = false"
            aria-hidden="true"
        ></div>

        <aside
            class="sidebar fixed inset-y-0 left-0 z-40 flex w-[280px] -translate-x-full flex-col border-r border-white/10 bg-slate-950/55 p-5 backdrop-blur-2xl transition-transform duration-300 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            :class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="mb-5 flex items-center gap-1.5 px-2 pt-1" aria-hidden="true">
                <span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
            </div>
            <a href="/dashboard" class="mb-9 flex items-center gap-3 px-2" aria-label="RUANG NOC NAT WAIPI">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-rose-300/15 text-rose-200 ring-1 ring-rose-200/25">
                    <svg class="h-7 w-7" viewBox="0 0 64 64" fill="none" aria-hidden="true">
                        <path d="M32 5 53 13v16c0 14-8 24-21 30C19 53 11 43 11 29V13L32 5Z" fill="#0f172a" stroke="#fb7185" stroke-width="2.5" stroke-linejoin="round"/>
                        <path d="M21 32h8l5-9 7 18 4-9h-3" stroke="#67e8f9" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="21" cy="32" r="2.5" fill="#fb7185"/>
                        <circle cx="45" cy="32" r="2.5" fill="#fb7185"/>
                    </svg>
                </span>
                <span>
                    <span class="block text-sm font-semibold tracking-wide text-white">RUANG NOC NAT WAIPI</span>
                    <span class="mt-0.5 block text-xs text-slate-400">Network operations</span>
                </span>
                <button type="button" class="ml-auto rounded-lg p-2 text-slate-400 hover:bg-white/10 lg:hidden" @click="sidebarOpen = false" aria-label="Tutup menu">
                    <span aria-hidden="true">×</span>
                </button>
            </a>

            <div class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-slate-500">Workspace</div>
            <nav class="space-y-1" aria-label="Navigasi utama">
                @if(($mode ?? 'managed') === 'mandiri')
                    <a href="/mandiri/provision" @click="sidebarOpen = false" class="nav-link {{ ($activePage ?? '') === 'provision' ? 'nav-link-active' : '' }}">
                        <span class="nav-icon">＋</span><span>Provisioning</span>
                    </a>
                    <a href="/mandiri/connection" @click="sidebarOpen = false" class="nav-link {{ ($activePage ?? '') === 'connection' ? 'nav-link-active' : '' }}">
                        <span class="nav-icon">⌁</span><span>Koneksi router</span>
                    </a>
                @else
                    <a href="/dashboard" @click="sidebarOpen = false" class="nav-link {{ ($activePage ?? '') === 'dashboard' ? 'nav-link-active' : '' }}">
                        <span class="nav-icon">▦</span><span>Dashboard</span>
                    </a>
                    <a href="/workspaces" @click="sidebarOpen = false" class="nav-link {{ in_array(($activePage ?? ''), ['workspaces', 'accounts', 'create'], true) ? 'nav-link-active' : '' }}">
                        <span class="nav-icon">◫</span><span>Workspaces</span>
                    </a>
                    <a href="/preferences" @click="sidebarOpen = false" class="nav-link {{ ($activePage ?? '') === 'preferences' ? 'nav-link-active' : '' }}">
                        <span class="nav-icon">⚙</span><span>Preferensi</span>
                    </a>
                @endif
            </nav>

            <div class="mt-auto rounded-2xl border border-white/10 bg-white/[.04] p-4">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-200">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-60"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-300"></span>
                    </span>
                    {{ ($mode ?? 'managed') === 'mandiri' ? 'Mode Mandiri' : 'Managed CHR' }}
                </div>
                <p class="mt-2 text-xs leading-5 text-slate-400">
                    {{ ($mode ?? 'managed') === 'mandiri' ? 'Kredensial router hanya digunakan selama sesi provisioning.' : 'Node utama dipilih otomatis; akun VPN dikelola di seluruh cluster.' }}
                </p>
                @if(($mode ?? 'managed') === 'managed')
                    <form method="POST" action="/logout" class="mt-3">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-cyan-200 hover:text-white">Keluar <span class="ml-1" aria-hidden="true">→</span></button>
                    </form>
                @endif
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="flex h-[76px] items-center justify-between border-b border-white/10 px-4 sm:px-7 lg:px-10">
                <div class="flex items-center gap-3">
                    <button type="button" class="rounded-xl border border-white/10 bg-white/[.06] p-2.5 text-slate-200 lg:hidden" @click="sidebarOpen = true" aria-label="Buka menu">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                    <div class="hidden text-xs text-slate-400 sm:block">NOC <span class="px-1 text-slate-600">/</span> @yield('breadcrumb', 'Overview')</div>
                    <div class="text-sm font-medium text-white sm:hidden">@yield('mobile-heading', 'RUANG NOC NAT WAIPI')</div>
                </div>
                <div class="flex items-center gap-3">
                    @if(($mode ?? 'managed') === 'managed')
                        <span class="hidden items-center gap-2 rounded-full border border-white/10 bg-white/[.05] px-3 py-2 text-xs text-slate-200 sm:inline-flex">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-300"></span>
                            {{ data_get($selectedServer ?? null, 'name', 'Cluster SSTP') }}
                        </span>
                    @endif
                    <div class="relative">
                        <button type="button" @click="profileOpen = !profileOpen" @click.outside="profileOpen = false" class="flex items-center gap-2 rounded-full border border-white/10 bg-white/[.06] p-1.5 pr-3 hover:bg-white/10" aria-label="Menu pengguna">
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-cyan-200 to-blue-400 text-xs font-bold text-slate-950">{{ mb_substr(auth()->user()->name ?? 'N', 0, 1) }}</span>
                            <span class="hidden max-w-32 truncate text-xs font-medium text-slate-200 sm:block">{{ auth()->user()->name ?? 'Network Operator' }}</span>
                            <span class="text-xs text-slate-400" aria-hidden="true">⌄</span>
                        </button>
                        <div x-cloak x-show="profileOpen" x-transition class="glass-panel absolute right-0 top-12 z-20 w-44 p-2">
                            <a href="/preferences" class="block rounded-xl px-3 py-2 text-sm text-slate-200 hover:bg-white/10">Preferensi</a>
                            <form method="POST" action="{{ ($mode ?? 'managed') === 'mandiri' ? '/mandiri/logout' : '/logout' }}">
                                @csrf
                                <button type="submit" class="w-full rounded-xl px-3 py-2 text-left text-sm text-rose-200 hover:bg-rose-400/10">Keluar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            @if(session('status'))
                <div class="mx-4 mt-5 rounded-2xl border border-emerald-300/20 bg-emerald-300/10 px-4 py-3 text-sm text-emerald-100 sm:mx-7 lg:mx-10" role="status">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="mx-4 mt-5 rounded-2xl border border-rose-300/20 bg-rose-300/10 px-4 py-3 text-sm text-rose-100 sm:mx-7 lg:mx-10" role="alert">
                    <p class="font-medium">Periksa kembali data yang dimasukkan.</p>
                    <ul class="mt-2 list-inside list-disc text-rose-100/80">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="mx-auto max-w-[1600px] px-4 py-7 sm:px-7 sm:py-9 lg:px-10">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
