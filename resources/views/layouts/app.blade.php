<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>@yield('title', 'IoTR // NEXUS — Control Center')</title>

    {{-- Fonts: Plus Jakarta Sans (UI) & JetBrains Mono (telemetri / kode) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Tailwind CSS v3 via CDN (tanpa Vite / Node.js build) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
                    },
                },
            },
        };
    </script>

    <style>
        html { color-scheme: dark; }
        body { background-color: #0a0a0b; }

        /* Kedip keras on/off (indikator alarm), bukan efek fade/glow */
        .blink { animation: blink 1s steps(1, end) infinite; }
        @keyframes blink { 50% { opacity: 0; } }

        /* Border alarm berganti merah terang / merah gelap */
        .alarm-border { animation: alarm-border 1s steps(1, end) infinite; }
        @keyframes alarm-border {
            0%   { border-color: #dc2626; }
            50%  { border-color: #450a0a; }
        }

        /* Garis skala 10% untuk bar indikator */
        .scale-ticks {
            background-image: repeating-linear-gradient(
                to right,
                transparent 0,
                transparent calc(10% - 1px),
                rgba(9, 9, 11, 0.9) calc(10% - 1px),
                rgba(9, 9, 11, 0.9) 10%
            );
        }

        .tabular { font-variant-numeric: tabular-nums; }

        ::selection { background: #10b981; color: #09090b; }

        @media (prefers-reduced-motion: reduce) {
            .blink, .alarm-border { animation: none; }
        }
    </style>

    @stack('head')
</head>
<body class="flex min-h-full flex-col font-sans text-zinc-300 antialiased overflow-x-hidden">

@auth
    {{-- ============================================================== --}}
    {{-- TOP BAR — CONTROL CENTER                                         --}}
    {{-- ============================================================== --}}
    <header class="sticky top-0 z-40 border-b border-zinc-800 bg-zinc-950">
        <div class="mx-auto flex h-14 max-w-[1600px] items-stretch justify-between px-3 sm:px-6 lg:px-8">

            {{-- Brand --}}
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 sm:gap-3 border-r-0 md:border-r border-zinc-800 pr-0 md:pr-5 shrink-0">
                <span class="grid h-7 w-7 sm:h-8 sm:w-8 place-items-center rounded-sm border border-zinc-700 bg-zinc-900">
                    <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 17a8 8 0 0 1 16 0" stroke="#a1a1aa" stroke-linecap="square"/>
                        <path d="M12 17l4-5" stroke="#10b981" stroke-linecap="square"/>
                        <circle cx="12" cy="17" r="1.6" fill="#f4f4f5" stroke="none"/>
                    </svg>
                </span>
                <span class="leading-tight">
                    <span class="block font-mono text-xs sm:text-sm font-bold tracking-tight text-zinc-100">IoTR <span class="text-zinc-500">//</span> NEXUS</span>
                    <span class="block font-mono text-[8px] sm:text-[9px] uppercase tracking-[0.16em] sm:tracking-[0.22em] text-zinc-500">Control Center</span>
                </span>
            </a>

            {{-- Segmen status desktop --}}
            <div class="hidden items-stretch md:flex">
                <div id="global-sync-indicator" class="flex flex-col justify-center border-r border-zinc-800 px-4 lg:px-5">
                    <span class="font-mono text-[9px] uppercase tracking-wider text-zinc-500">Telemetry Link</span>
                    <span class="mt-0.5 flex items-center gap-2 font-mono text-[11px] font-semibold text-zinc-300">
                        <span id="global-sync-dot" class="inline-block h-2 w-2 shrink-0 rounded-full bg-zinc-600"></span>
                        <span id="global-sync-text">STANDBY</span>
                    </span>
                </div>
                <div class="hidden flex-col justify-center border-r border-zinc-800 px-4 lg:px-5 lg:flex">
                    <span class="font-mono text-[9px] uppercase tracking-wider text-zinc-500">Server</span>
                    <span class="mt-0.5 flex items-center gap-2 font-mono text-[11px] font-semibold text-zinc-300">
                        <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                        ONLINE
                    </span>
                </div>
                <div class="hidden flex-col justify-center border-r border-zinc-800 px-4 lg:px-5 xl:flex">
                    <span class="font-mono text-[9px] uppercase tracking-wider text-zinc-500">Protokol</span>
                    <span class="mt-0.5 font-mono text-[11px] font-semibold text-zinc-300">ESP-NOW + HTTP</span>
                </div>
                <div class="hidden flex-col justify-center border-r border-zinc-800 px-4 lg:px-5 xl:flex">
                    <span class="font-mono text-[9px] uppercase tracking-wider text-zinc-500">Waktu Lokal</span>
                    <span id="nav-clock" class="tabular mt-0.5 font-mono text-[11px] font-semibold text-zinc-300">--:--:--</span>
                </div>
            </div>

            {{-- Operator & Logout --}}
            <div class="ml-auto flex items-center gap-2.5 sm:gap-4 pl-2 sm:pl-4">
                {{-- Compact Sync Pill on Mobile Screen --}}
                <div class="flex items-center gap-1.5 md:hidden font-mono text-[9px] text-zinc-400 bg-zinc-900 border border-zinc-800 px-2 py-1 rounded-sm">
                    <span id="global-sync-dot-mobile" class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-zinc-600"></span>
                    <span class="uppercase tracking-wider text-zinc-500">Live</span>
                </div>

                <div class="hidden items-center gap-3 sm:flex">
                    <span class="grid h-8 w-8 place-items-center rounded-sm border border-zinc-700 bg-zinc-900 font-mono text-[11px] font-bold text-zinc-200">
                        {{ strtoupper(mb_substr(Auth::user()->name, 0, 2)) }}
                    </span>
                    <span class="leading-tight">
                        <span class="block max-w-[140px] md:max-w-[180px] truncate text-xs font-semibold text-zinc-100">{{ Auth::user()->name }}</span>
                        <span class="block max-w-[140px] md:max-w-[180px] truncate font-mono text-[10px] text-zinc-500">{{ Auth::user()->email }}</span>
                    </span>
                </div>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="inline-flex h-8 items-center gap-1.5 sm:gap-2 rounded-sm border border-zinc-700 bg-zinc-900 px-2.5 sm:px-3 font-mono text-[10px] sm:text-[11px] font-semibold uppercase tracking-wider text-zinc-300 transition-colors hover:border-red-600 hover:bg-red-600 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 active:translate-y-px">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" stroke-linecap="square"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </header>
@else
    {{-- Top bar minimal untuk halaman tamu --}}
    <header class="border-b border-zinc-800 bg-zinc-950">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-3 sm:px-6 lg:px-8">
            <a href="{{ route('login') }}" class="flex items-center gap-2.5 sm:gap-3">
                <span class="grid h-7 w-7 sm:h-8 sm:w-8 place-items-center rounded-sm border border-zinc-700 bg-zinc-900">
                    <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke-width="1.8" aria-hidden="true">
                        <path d="M4 17a8 8 0 0 1 16 0" stroke="#a1a1aa" stroke-linecap="square"/>
                        <path d="M12 17l4-5" stroke="#10b981" stroke-linecap="square"/>
                        <circle cx="12" cy="17" r="1.6" fill="#f4f4f5" stroke="none"/>
                    </svg>
                </span>
                <span class="font-mono text-xs sm:text-sm font-bold tracking-tight text-zinc-100">IoTR <span class="text-zinc-500">//</span> NEXUS</span>
            </a>
            <span class="flex items-center gap-1.5 sm:gap-2 font-mono text-[9px] sm:text-[10px] uppercase tracking-wider text-zinc-500">
                <span class="inline-block h-1.5 w-1.5 sm:h-2 sm:w-2 rounded-full bg-emerald-500"></span>
                Secure Access
            </span>
        </div>
    </header>
@endauth

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="border-t border-zinc-800 bg-zinc-950">
        <div class="mx-auto flex max-w-[1600px] flex-col gap-1.5 px-3 py-3 sm:py-4 font-mono text-[9px] sm:text-[10px] uppercase tracking-wider text-zinc-600 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <span class="break-words">IoTR // NEXUS · Smart Bell Outdoor &amp; Indoor Gas Mechatronics Monitoring</span>
            <span class="shrink-0">&copy; {{ date('Y') }}</span>
        </div>
    </footer>

@auth
    <script>
        (function () {
            var el = document.getElementById('nav-clock');
            if (!el) return;
            function tick() {
                el.textContent = new Date().toLocaleTimeString('id-ID', { hour12: false });
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
@endauth

    @stack('scripts')
</body>
</html>
