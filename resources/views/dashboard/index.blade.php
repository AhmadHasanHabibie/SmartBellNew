@extends('layouts.app')

@section('title', 'Control Room — IoTR // NEXUS')

@php
    /*
    |--------------------------------------------------------------------------
    | Design tokens (dipakai bersama oleh Blade & JavaScript lewat @js)
    |--------------------------------------------------------------------------
    */
    $base = [
        'card'  => 'flex flex-col gap-3 rounded-sm border p-4 transition-colors duration-200',
        'badge' => 'inline-flex items-center gap-1.5 rounded-sm px-2 py-0.5 font-mono text-[10px] font-bold uppercase tracking-wider',
        'led'   => 'inline-block h-2 w-2 shrink-0 rounded-full',
        'bar'   => 'h-full transition-[width] duration-500',
        'text'  => 'font-mono text-[11px] font-bold uppercase tracking-wider',
    ];

    $tone = [
        'idle'   => ['card' => 'border-zinc-800 bg-zinc-900',              'badge' => 'bg-zinc-800 text-zinc-300 ring-1 ring-inset ring-zinc-700', 'led' => 'bg-zinc-600',          'text' => 'text-zinc-400',    'bar' => 'bg-zinc-500'],
        'ok'     => ['card' => 'border-zinc-800 bg-zinc-900',              'badge' => 'bg-emerald-600 text-zinc-950',                              'led' => 'bg-emerald-500',       'text' => 'text-emerald-400', 'bar' => 'bg-emerald-500'],
        'active' => ['card' => 'border-emerald-600 bg-emerald-950/40',     'badge' => 'bg-emerald-500 text-zinc-950',                              'led' => 'bg-emerald-400 blink', 'text' => 'text-emerald-300', 'bar' => 'bg-emerald-500'],
        'warn'   => ['card' => 'border-amber-500 bg-amber-950/30',         'badge' => 'bg-amber-500 text-zinc-950',                                'led' => 'bg-amber-400',         'text' => 'text-amber-400',   'bar' => 'bg-amber-500'],
        'danger' => ['card' => 'border-red-600 bg-red-950/60 alarm-border', 'badge' => 'bg-red-600 text-white blink',                              'led' => 'bg-red-500 blink',     'text' => 'text-red-400',     'bar' => 'bg-red-600'],
    ];

    /*
    |--------------------------------------------------------------------------
    | Logika status (dicerminkan 1:1 pada JavaScript polling di bawah)
    |--------------------------------------------------------------------------
    */
    $gasState = function (int $gas): array {
        if ($gas >= 3000) {
            return ['tone' => 'danger', 'label' => 'BAHAYA · SIRINE AKTIF'];
        }
        if ($gas >= 1500) {
            return ['tone' => 'warn', 'label' => 'WASPADA'];
        }
        return ['tone' => 'ok', 'label' => 'AMAN'];
    };

    $servoZoneState = function (int $angle): array {
        if ($angle >= 120) {
            return ['tone' => 'danger', 'label' => 'ZONA MERAH'];
        }
        if ($angle >= 60) {
            return ['tone' => 'warn', 'label' => 'ZONA KUNING'];
        }
        return ['tone' => 'ok', 'label' => 'ZONA HIJAU'];
    };

    $outdoorBellState = function (int $bel): array {
        return match ($bel) {
            1 => ['tone' => 'active', 'label' => 'TAMU DATANG', 'desc' => '1x klik · Silakan masuk',      'sub' => 'ESP-NOW → Indoor: nada TING-TUNG'],
            2 => ['tone' => 'warn',   'label' => 'KURIR PAKET', 'desc' => '3x klik · Taruh di gerbang',   'sub' => 'ESP-NOW → Indoor: BEEP 3x'],
            default => ['tone' => 'idle', 'label' => 'STANDBY', 'desc' => 'Menunggu input tombol KY-004', 'sub' => 'Tidak ada panggilan aktif'],
        };
    };

    $indoorBellState = function (int $gas, int $bel, float $suhu, float $rh): array {
        if ($gas >= 3000) {
            return ['tone' => 'danger', 'label' => 'SIRINE DARURAT', 'desc' => 'Buzzer D4 · alarm kontinu', 'tft' => '!! BAHAYA GAS BOCOR !! EVAKUASI SEGERA'];
        }
        if ($bel === 1) {
            return ['tone' => 'active', 'label' => 'TING-TUNG', 'desc' => 'Buzzer D4 · nada tamu', 'tft' => 'TAMU DATANG - SILAKAN MASUK'];
        }
        if ($bel === 2) {
            return ['tone' => 'warn', 'label' => 'BEEP 3x', 'desc' => 'Buzzer D4 · nada kurir', 'tft' => 'KURIR PAKET - TARUH DI GERBANG'];
        }
        return ['tone' => 'idle', 'label' => 'STANDBY', 'desc' => 'Buzzer D4 · senyap', 'tft' => sprintf('T %.1fC  RH %.1f%%  GAS %d', $suhu, $rh, $gas)];
    };

    $solarDirection = function (int $timur, int $barat): string {
        $delta = $timur - $barat;
        if (abs($delta) <= 120) {
            return 'SEIMBANG';
        }
        return $delta > 0 ? 'CONDONG TIMUR' : 'CONDONG BARAT';
    };

    // Status bel aktif dari unit outdoor (diteruskan ke indoor via ESP-NOW)
    $outdoorBellLive = (int) ($devices
        ->where('device_type', 'outdoor')
        ->map(fn ($d) => (int) optional($d->latestTelemetry)->status_bel)
        ->max() ?? 0);

    // Status keamanan global
    if ($hasGasDanger) {
        $globalState = ['tone' => 'danger', 'label' => 'BAHAYA · GAS BOCOR'];
    } elseif ($hasGasWarning) {
        $globalState = ['tone' => 'warn', 'label' => 'WASPADA · GAS NAIK'];
    } elseif ($activeBellStatus === 1) {
        $globalState = ['tone' => 'active', 'label' => 'TAMU DI GERBANG'];
    } elseif ($activeBellStatus === 2) {
        $globalState = ['tone' => 'warn', 'label' => 'KURIR PAKET'];
    } else {
        $globalState = ['tone' => 'ok', 'label' => 'AMAN · TERKENDALI'];
    }

    $inputClass = 'h-10 w-full rounded-sm border bg-zinc-950 px-3 text-sm text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500';
@endphp

@section('content')
<div class="mx-auto max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">

    {{-- ================================================================== --}}
    {{-- JUDUL HALAMAN                                                        --}}
    {{-- ================================================================== --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-zinc-500">Control Room / Overview</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight text-zinc-100">Monitoring Perangkat</h1>
        </div>
        <p class="font-mono text-[11px] text-zinc-500">Polling 3 s · Riwayat 20 sampel / unit</p>
    </div>

    {{-- ================================================================== --}}
    {{-- FLASH MESSAGE                                                        --}}
    {{-- ================================================================== --}}
    @if (session('success'))
        <div class="flex items-center gap-3 border border-l-4 border-zinc-800 border-l-emerald-500 bg-zinc-900 px-4 py-3" role="status">
            <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-emerald-400">OK</span>
            <span class="text-sm text-zinc-200">{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="flex items-center gap-3 border border-l-4 border-zinc-800 border-l-red-600 bg-zinc-900 px-4 py-3" role="alert">
            <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-red-400">ERR</span>
            <span class="text-sm text-zinc-200">{{ session('error') }}</span>
        </div>
    @endif

    @if (session('info'))
        <div class="flex items-center gap-3 border border-l-4 border-zinc-800 border-l-sky-500 bg-zinc-900 px-4 py-3" role="status">
            <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-sky-400">INFO</span>
            <span class="text-sm text-zinc-200">{{ session('info') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="border border-l-4 border-zinc-800 border-l-red-600 bg-zinc-900 px-4 py-3" role="alert">
            <p class="font-mono text-[10px] font-bold uppercase tracking-wider text-red-400">ERR · Input Pairing Tidak Valid</p>
            <ul class="mt-1 space-y-0.5 text-sm text-zinc-200">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ================================================================== --}}
    {{-- BANNER ALARM GAS BOCOR                                               --}}
    {{-- ================================================================== --}}
    <div id="emergency-banner" class="{{ $hasGasDanger ? 'flex' : 'hidden' }} items-stretch border-2 border-red-600 bg-red-950" role="alert">
        <div class="flex w-14 shrink-0 items-center justify-center bg-red-600">
            <svg class="h-7 w-7 text-zinc-950" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                <path d="M12 3 2 21h20L12 3Z" stroke-linejoin="miter"/>
                <path d="M12 10v5M12 17.5v.5" stroke-linecap="square"/>
            </svg>
        </div>
        <div class="min-w-0 flex-1 px-4 py-3">
            <p class="font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-red-300">
                <span class="blink">■</span> Alarm · Critical Gas Hazard · MQ-2 &ge; 3000 ADC
            </p>
            <p class="mt-1 text-base font-bold text-white">Kebocoran gas terdeteksi pada unit indoor.</p>
            <p id="emergency-banner-desc" class="mt-0.5 text-xs text-red-200">
                Jarum servo gauge berada di zona merah dan sirine buzzer D4 aktif. Segera buka ventilasi dan putus sumber gas.
            </p>
        </div>
        <div class="hidden shrink-0 flex-col justify-center gap-1 border-l border-red-800 px-5 font-mono text-[11px] font-bold uppercase text-red-200 sm:flex">
            <span>Sirine D4 · <span class="blink">ON</span></span>
            <span>Servo · 160°</span>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- STRIP STATUS GLOBAL                                                  --}}
    {{-- ================================================================== --}}
    <section class="grid grid-cols-2 gap-px border border-zinc-800 bg-zinc-800 lg:grid-cols-5" aria-label="Ringkasan status">
        <div class="bg-zinc-950 px-4 py-3">
            <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Perangkat Terklaim</p>
            <p id="stat-total-devices" class="tabular mt-1 font-mono text-2xl font-semibold text-zinc-100">{{ $totalDevices }}<span class="ml-1 text-xs font-normal text-zinc-500">/ 2 node</span></p>
        </div>
        <div class="bg-zinc-950 px-4 py-3">
            <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Unit Outdoor</p>
            <p id="stat-outdoor-count" class="tabular mt-1 font-mono text-2xl font-semibold text-zinc-100">{{ $outdoorCount }}<span class="ml-1 text-xs font-normal text-zinc-500">unit</span></p>
        </div>
        <div class="bg-zinc-950 px-4 py-3">
            <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Unit Indoor</p>
            <p id="stat-indoor-count" class="tabular mt-1 font-mono text-2xl font-semibold text-zinc-100">{{ $indoorCount }}<span class="ml-1 text-xs font-normal text-zinc-500">unit</span></p>
        </div>
        <div class="bg-zinc-950 px-4 py-3">
            <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Link Online</p>
            <p class="tabular mt-1 font-mono text-2xl font-semibold text-zinc-100"><span id="stat-online-count">{{ $onlineCount }}</span><span class="ml-1 text-xs font-normal text-zinc-500">/ {{ $totalDevices }}</span></p>
        </div>
        <div class="col-span-2 bg-zinc-950 px-4 py-3 lg:col-span-1">
            <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Status Keamanan</p>
            <p class="mt-2">
                <span id="global-security-badge" class="{{ $base['badge'] }} {{ $tone[$globalState['tone']]['badge'] }}">{{ $globalState['label'] }}</span>
            </p>
        </div>
    </section>

    {{-- ================================================================== --}}
    {{-- P-01 · PAIRING PERANGKAT                                             --}}
    {{-- ================================================================== --}}
    <section class="rounded-sm border border-zinc-800 bg-zinc-950" aria-labelledby="pairing-title">
        <div class="flex flex-col gap-3 border-b border-zinc-800 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="font-mono text-[10px] text-zinc-600">P-01</span>
                <h2 id="pairing-title" class="text-sm font-semibold text-zinc-100">Pairing Perangkat</h2>
                <span class="hidden font-mono text-[10px] uppercase tracking-wider text-zinc-500 md:inline">Klaim unit pabrikan dengan S/N + password pelat data</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="font-mono text-[10px] uppercase tracking-wider text-zinc-600">Quick Fill</span>
                <button type="button" onclick="quickFillDevice('outdoor')"
                    class="h-8 rounded-sm border border-zinc-700 bg-zinc-900 px-3 font-mono text-[11px] font-semibold text-zinc-300 transition-colors hover:border-sky-500 hover:text-sky-300 active:translate-y-px">
                    Demo Outdoor
                </button>
                <button type="button" onclick="quickFillDevice('indoor')"
                    class="h-8 rounded-sm border border-zinc-700 bg-zinc-900 px-3 font-mono text-[11px] font-semibold text-zinc-300 transition-colors hover:border-emerald-500 hover:text-emerald-300 active:translate-y-px">
                    Demo Indoor
                </button>
            </div>
        </div>

        <form action="{{ route('device.pair') }}" method="POST" class="grid grid-cols-1 items-end gap-4 p-5 md:grid-cols-12">
            @csrf

            <div class="md:col-span-4">
                <label for="input-device-name" class="mb-1.5 flex items-center justify-between font-mono text-[10px] uppercase tracking-wider text-zinc-400">
                    <span>Nama / S/N Perangkat</span><span class="text-zinc-600">F1</span>
                </label>
                <input type="text" id="input-device-name" name="device_name" value="{{ old('device_name') }}" required autocomplete="off" spellcheck="false"
                    placeholder="Contoh: IOTR-INDOOR-01"
                    class="{{ $inputClass }} font-mono uppercase @error('device_name') border-red-600 @else border-zinc-700 @enderror">
            </div>

            <div class="md:col-span-3">
                <label for="input-device-password" class="mb-1.5 flex items-center justify-between font-mono text-[10px] uppercase tracking-wider text-zinc-400">
                    <span>Password (Belakang Alat)</span><span class="text-zinc-600">F2</span>
                </label>
                <div class="relative">
                    <input type="password" id="input-device-password" name="device_password" required autocomplete="off"
                        placeholder="••••••••"
                        class="{{ $inputClass }} pr-16 font-mono @error('device_password') border-red-600 @else border-zinc-700 @enderror">
                    <button type="button" id="toggle-device-password" onclick="toggleDevicePassword()"
                        class="absolute inset-y-0 right-0 px-3 font-mono text-[10px] font-semibold uppercase tracking-wider text-zinc-500 hover:text-zinc-200"
                        aria-label="Tampilkan password">Show</button>
                </div>
            </div>

            <div class="md:col-span-3">
                <label for="input-nama-ruangan" class="mb-1.5 flex items-center justify-between font-mono text-[10px] uppercase tracking-wider text-zinc-400">
                    <span>Lokasi Pemasangan</span><span class="text-zinc-600">F3</span>
                </label>
                <input type="text" id="input-nama-ruangan" name="nama_ruangan" value="{{ old('nama_ruangan') }}" required maxlength="50"
                    placeholder="Contoh: Gerbang Utama / Dapur"
                    class="{{ $inputClass }} @error('nama_ruangan') border-red-600 @else border-zinc-700 @enderror">
            </div>

            <div class="md:col-span-2">
                <button type="submit"
                    class="h-10 w-full rounded-sm bg-emerald-600 font-mono text-xs font-bold uppercase tracking-wider text-zinc-950 transition-colors hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 active:bg-emerald-700">
                    Klaim Alat
                </button>
            </div>
        </form>
    </section>

    {{-- ================================================================== --}}
    {{-- EMPTY STATE                                                          --}}
    {{-- ================================================================== --}}
    @if ($devices->isEmpty())
        <section class="rounded-sm border border-dashed border-zinc-700 bg-zinc-950 px-6 py-12"
            style="background-image: linear-gradient(#18181b 1px, transparent 1px), linear-gradient(90deg, #18181b 1px, transparent 1px); background-size: 24px 24px;">
            <div class="mx-auto max-w-2xl text-center">
                <p class="font-mono text-[10px] uppercase tracking-[0.2em] text-zinc-500">No Linked Nodes</p>
                <h3 class="mt-2 text-xl font-semibold text-zinc-100">Belum ada unit ESP32 yang terhubung ke akun ini.</h3>
                <p class="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-zinc-400">
                    Masukkan S/N dan password yang tertera di pelat data bodi belakang unit Outdoor maupun Indoor
                    pada panel <span class="font-mono text-zinc-200">P-01 · Pairing Perangkat</span> di atas.
                </p>
                <div class="mx-auto mt-6 max-w-xl text-left">
                    @include('partials.device-plate')
                </div>
            </div>
        </section>
    @else

    {{-- ================================================================== --}}
    {{-- AREA UTAMA: PANEL PERANGKAT + SIMULATOR                              --}}
    {{-- ================================================================== --}}
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">

        {{-- -------------------------------------------------------------- --}}
        {{-- PANEL PERANGKAT TERHUBUNG                                        --}}
        {{-- -------------------------------------------------------------- --}}
        <div class="space-y-6 xl:col-span-8">
            @foreach ($devices as $device)
                @php
                    $telemetry = $device->latestTelemetry;
                    $isOnline = $device->isOnline();
                    $lastSeenText = $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Belum pernah sinkron';
                    $isOutdoor = $device->device_type === 'outdoor';
                @endphp

                <section id="device-panel-{{ $device->id }}" data-device-id="{{ $device->id }}" data-device-type="{{ $device->device_type }}"
                    class="rounded-sm border border-zinc-800 bg-zinc-950">

                    {{-- Header panel --}}
                    <header class="flex flex-col gap-4 border-b border-zinc-800 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 font-mono text-[10px] uppercase tracking-wider">
                                @if ($isOutdoor)
                                    <span class="rounded-sm bg-sky-500 px-1.5 py-0.5 font-bold text-zinc-950">Outdoor</span>
                                    <span class="text-zinc-500">Smart Bell &amp; Solar Tracker</span>
                                @else
                                    <span class="rounded-sm bg-emerald-500 px-1.5 py-0.5 font-bold text-zinc-950">Indoor</span>
                                    <span class="text-zinc-500">Gas Mechatronics &amp; Env Monitor</span>
                                @endif
                            </div>
                            <h3 class="mt-1.5 truncate text-lg font-semibold text-zinc-100">
                                {{ $device->nama_ruangan ?? ($isOutdoor ? 'Unit Outdoor' : 'Unit Indoor') }}
                            </h3>
                            <p class="font-mono text-xs text-zinc-500">S/N <span class="text-zinc-300">{{ $device->device_name }}</span></p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex items-stretch divide-x divide-zinc-800 border border-zinc-800 bg-zinc-900 font-mono text-[11px]">
                                <div class="px-3 py-1.5">
                                    <span class="block text-[9px] uppercase tracking-wider text-zinc-500">Link</span>
                                    <span id="status-badge-{{ $device->id }}" class="inline-flex items-center gap-1.5 font-bold {{ $isOnline ? 'text-emerald-400' : 'text-red-400' }}">
                                        <span class="{{ $base['led'] }} {{ $isOnline ? 'bg-emerald-500' : 'bg-red-500' }}"></span>{{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
                                    </span>
                                </div>
                                <div class="px-3 py-1.5">
                                    <span class="block text-[9px] uppercase tracking-wider text-zinc-500">Last Seen</span>
                                    <span id="last-seen-{{ $device->id }}" class="text-zinc-300">{{ $lastSeenText }}</span>
                                </div>
                            </div>

                            <form action="{{ route('device.unpair', $device) }}" method="POST"
                                onsubmit="return confirm(@js('Lepas perangkat ' . $device->device_name . ' dari akun Anda?'));">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="h-[42px] rounded-sm border border-zinc-700 bg-zinc-900 px-3 font-mono text-[11px] font-semibold uppercase tracking-wider text-zinc-400 transition-colors hover:border-red-600 hover:text-red-400 active:translate-y-px">
                                    Unpair
                                </button>
                            </form>
                        </div>
                    </header>

                    @if ($isOutdoor)
                        {{-- ====================================================== --}}
                        {{-- OUTDOOR UNIT                                             --}}
                        {{-- ====================================================== --}}
                        @php
                            $statusBel = (int) ($telemetry->status_bel ?? 0);
                            $sudutSolar = (int) ($telemetry->sudut_solar ?? 90);
                            $ldrTimur = (int) ($telemetry->cahaya_timur ?? 0);
                            $ldrBarat = (int) ($telemetry->cahaya_barat ?? 0);
                            $bell = $outdoorBellState($statusBel);
                            $delta = $ldrTimur - $ldrBarat;
                        @endphp

                        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 2xl:grid-cols-4">

                            {{-- 01 · Smart Bell KY-004 --}}
                            <div id="outdoor-bel-card-{{ $device->id }}" class="{{ $base['card'] }} {{ $tone[$bell['tone']]['card'] }}">
                                <div class="flex items-center justify-between">
                                    <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500"><span class="text-zinc-600">01</span> · Smart Bell KY-004</p>
                                    <span id="outdoor-bel-led-{{ $device->id }}" class="{{ $base['led'] }} {{ $tone[$bell['tone']]['led'] }}"></span>
                                </div>
                                <div>
                                    <span id="outdoor-bel-text-{{ $device->id }}" class="{{ $base['badge'] }} {{ $tone[$bell['tone']]['badge'] }}">{{ $bell['label'] }}</span>
                                </div>
                                <p id="outdoor-bel-desc-{{ $device->id }}" class="text-sm font-medium text-zinc-100">{{ $bell['desc'] }}</p>
                                <div class="mt-auto flex items-center justify-between border-t border-zinc-800 pt-2 font-mono text-[10px] text-zinc-500">
                                    <span id="outdoor-bel-sub-{{ $device->id }}">{{ $bell['sub'] }}</span>
                                    <span>CODE <span id="outdoor-bel-code-{{ $device->id }}" class="text-zinc-300">{{ $statusBel }}</span></span>
                                </div>
                            </div>

                            {{-- 02 · Solar Tracker SG90 --}}
                            <div class="{{ $base['card'] }} {{ $tone['idle']['card'] }}">
                                <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500"><span class="text-zinc-600">02</span> · Solar Tracker SG90</p>
                                <div class="flex justify-center">
                                    @include('partials.gauge', [
                                        'needleId' => "outdoor-servo-needle-{$device->id}",
                                        'angle' => $sudutSolar,
                                        'variant' => 'neutral',
                                        'leftLabel' => 'T',
                                        'rightLabel' => 'B',
                                        'label' => 'Sudut servo solar tracker',
                                    ])
                                </div>
                                <div class="mt-auto flex items-end justify-between border-t border-zinc-800 pt-2">
                                    <span id="outdoor-servo-{{ $device->id }}" class="tabular font-mono text-2xl font-semibold text-zinc-100">{{ $sudutSolar }}°</span>
                                    <span id="outdoor-solar-dir-{{ $device->id }}" class="font-mono text-[10px] font-bold uppercase tracking-wider text-zinc-400">{{ $solarDirection($ldrTimur, $ldrBarat) }}</span>
                                </div>
                            </div>

                            {{-- 03 · LDR Timur --}}
                            <div class="{{ $base['card'] }} {{ $tone['idle']['card'] }}">
                                <div class="flex items-center justify-between">
                                    <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500"><span class="text-zinc-600">03</span> · LDR Timur</p>
                                    <span class="h-2 w-2 bg-amber-500" aria-hidden="true"></span>
                                </div>
                                <p class="flex items-baseline gap-1.5">
                                    <span id="outdoor-ldr-timur-{{ $device->id }}" class="tabular font-mono text-3xl font-semibold text-zinc-100">{{ $ldrTimur }}</span>
                                    <span class="font-mono text-xs text-zinc-500">/ 4095 ADC</span>
                                </p>
                                <div class="mt-auto">
                                    <div class="relative h-2 w-full overflow-hidden bg-zinc-800">
                                        <div id="outdoor-ldr-timur-bar-{{ $device->id }}" class="{{ $base['bar'] }} bg-amber-500" style="width: {{ max(0, min(100, round($ldrTimur / 4095 * 100))) }}%;"></div>
                                        <div class="scale-ticks pointer-events-none absolute inset-0"></div>
                                    </div>
                                    <div class="mt-1 flex justify-between font-mono text-[9px] text-zinc-600"><span>0</span><span>2048</span><span>4095</span></div>
                                </div>
                            </div>

                            {{-- 04 · LDR Barat --}}
                            <div class="{{ $base['card'] }} {{ $tone['idle']['card'] }}">
                                <div class="flex items-center justify-between">
                                    <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500"><span class="text-zinc-600">04</span> · LDR Barat</p>
                                    <span class="h-2 w-2 bg-sky-400" aria-hidden="true"></span>
                                </div>
                                <p class="flex items-baseline gap-1.5">
                                    <span id="outdoor-ldr-barat-{{ $device->id }}" class="tabular font-mono text-3xl font-semibold text-zinc-100">{{ $ldrBarat }}</span>
                                    <span class="font-mono text-xs text-zinc-500">/ 4095 ADC</span>
                                </p>
                                <div class="mt-auto">
                                    <div class="relative h-2 w-full overflow-hidden bg-zinc-800">
                                        <div id="outdoor-ldr-barat-bar-{{ $device->id }}" class="{{ $base['bar'] }} bg-sky-400" style="width: {{ max(0, min(100, round($ldrBarat / 4095 * 100))) }}%;"></div>
                                        <div class="scale-ticks pointer-events-none absolute inset-0"></div>
                                    </div>
                                    <div class="mt-1 flex justify-between font-mono text-[9px] text-zinc-600">
                                        <span>0</span>
                                        <span>Δ T−B <span id="outdoor-ldr-delta-{{ $device->id }}" class="text-zinc-400">{{ $delta > 0 ? '+' : '' }}{{ $delta }}</span></span>
                                        <span>4095</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Grafik outdoor --}}
                        <div class="border-t border-zinc-800 px-4 pb-4 pt-3">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Trend · Solar Tracker &amp; Fotodioda</p>
                                <div class="flex items-center gap-4 font-mono text-[10px] text-zinc-400">
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-amber-500"></span>LDR Timur</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-sky-400"></span>LDR Barat</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-px w-3 border-t border-dashed border-zinc-200"></span>Servo (°)</span>
                                </div>
                            </div>
                            <div class="relative h-56 border border-zinc-800 bg-zinc-950 p-2">
                                <canvas id="chart-outdoor-{{ $device->id }}" aria-label="Grafik telemetri outdoor" role="img"></canvas>
                            </div>
                        </div>
                    @else
                        {{-- ====================================================== --}}
                        {{-- INDOOR UNIT                                              --}}
                        {{-- ====================================================== --}}
                        @php
                            $kadarGas = (int) ($telemetry->kadar_gas ?? 0);
                            $sudutServoGas = (int) ($telemetry->sudut_servo_gas ?? 20);
                            $suhuVal = (float) ($telemetry->suhu ?? 0);
                            $rhVal = (float) ($telemetry->kelembapan ?? 0);
                            $statusBelIndoor = max((int) ($telemetry->status_bel ?? 0), $outdoorBellLive);
                            $gas = $gasState($kadarGas);
                            $zone = $servoZoneState($sudutServoGas);
                            $buzzer = $indoorBellState($kadarGas, $statusBelIndoor, $suhuVal, $rhVal);
                        @endphp

                        <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 2xl:grid-cols-4">

                            {{-- 01 · Kadar Gas MQ-2 --}}
                            <div id="indoor-gas-card-{{ $device->id }}" class="{{ $base['card'] }} {{ $tone[$gas['tone']]['card'] }}">
                                <div class="flex items-center justify-between">
                                    <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500"><span class="text-zinc-600">01</span> · Gas MQ-2</p>
                                    <span id="indoor-gas-led-{{ $device->id }}" class="{{ $base['led'] }} {{ $tone[$gas['tone']]['led'] }}"></span>
                                </div>
                                <p class="flex items-baseline gap-1.5">
                                    <span id="indoor-gas-val-{{ $device->id }}" class="tabular font-mono text-3xl font-semibold text-zinc-100">{{ $kadarGas }}</span>
                                    <span class="font-mono text-xs text-zinc-500">ADC</span>
                                </p>
                                <div>
                                    <span id="indoor-gas-status-{{ $device->id }}" class="{{ $base['badge'] }} {{ $tone[$gas['tone']]['badge'] }}">{{ $gas['label'] }}</span>
                                </div>
                                <div class="mt-auto">
                                    <div class="relative h-2 w-full overflow-hidden bg-zinc-800">
                                        <div id="indoor-gas-bar-{{ $device->id }}" class="{{ $base['bar'] }} {{ $tone[$gas['tone']]['bar'] }}" style="width: {{ max(0, min(100, round($kadarGas / 4095 * 100))) }}%;"></div>
                                        <span class="absolute inset-y-0 w-px bg-amber-400" style="left: 36.6%;" aria-hidden="true"></span>
                                        <span class="absolute inset-y-0 w-px bg-red-500" style="left: 73.3%;" aria-hidden="true"></span>
                                    </div>
                                    <div class="relative mt-1 h-3 font-mono text-[9px] text-zinc-600">
                                        <span class="absolute left-0">0</span>
                                        <span class="absolute -translate-x-1/2 text-amber-500/80" style="left: 36.6%;">1500</span>
                                        <span class="absolute -translate-x-1/2 text-red-500/80" style="left: 73.3%;">3000</span>
                                        <span class="absolute right-0">4095</span>
                                    </div>
                                </div>
                            </div>

                            {{-- 02 · Analog Gauge Servo D13 --}}
                            <div class="{{ $base['card'] }} {{ $tone['idle']['card'] }}">
                                <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500"><span class="text-zinc-600">02</span> · Gauge Servo D13</p>
                                <div class="flex justify-center">
                                    @include('partials.gauge', [
                                        'needleId' => "indoor-servo-gas-needle-{$device->id}",
                                        'angle' => $sudutServoGas,
                                        'variant' => 'zones',
                                        'leftLabel' => '0°',
                                        'rightLabel' => '180°',
                                        'label' => 'Posisi jarum servo gauge gas',
                                    ])
                                </div>
                                <div class="mt-auto flex items-end justify-between border-t border-zinc-800 pt-2">
                                    <span id="indoor-servo-gas-{{ $device->id }}" class="tabular font-mono text-2xl font-semibold text-zinc-100">{{ $sudutServoGas }}°</span>
                                    <span id="indoor-servo-zone-{{ $device->id }}" class="{{ $base['text'] }} {{ $tone[$zone['tone']]['text'] }}">{{ $zone['label'] }}</span>
                                </div>
                            </div>

                            {{-- 03 · DHT22 --}}
                            <div class="{{ $base['card'] }} {{ $tone['idle']['card'] }}">
                                <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500"><span class="text-zinc-600">03</span> · Env DHT22</p>
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex items-baseline justify-between">
                                            <span class="font-mono text-[10px] uppercase text-zinc-500">Suhu</span>
                                            <span id="indoor-suhu-{{ $device->id }}" class="tabular font-mono text-2xl font-semibold text-zinc-100">{{ number_format($suhuVal, 1) }}°C</span>
                                        </div>
                                        <div class="relative mt-1 h-1.5 w-full overflow-hidden bg-zinc-800">
                                            <div id="indoor-suhu-bar-{{ $device->id }}" class="{{ $base['bar'] }} bg-sky-400" style="width: {{ max(0, min(100, round($suhuVal / 50 * 100))) }}%;"></div>
                                            <div class="scale-ticks pointer-events-none absolute inset-0"></div>
                                        </div>
                                        <div class="mt-0.5 flex justify-between font-mono text-[9px] text-zinc-600"><span>0</span><span>50°C</span></div>
                                    </div>
                                    <div>
                                        <div class="flex items-baseline justify-between">
                                            <span class="font-mono text-[10px] uppercase text-zinc-500">Kelembapan</span>
                                            <span id="indoor-kelembapan-{{ $device->id }}" class="tabular font-mono text-2xl font-semibold text-zinc-100">{{ number_format($rhVal, 1) }}%</span>
                                        </div>
                                        <div class="relative mt-1 h-1.5 w-full overflow-hidden bg-zinc-800">
                                            <div id="indoor-kelembapan-bar-{{ $device->id }}" class="{{ $base['bar'] }} bg-zinc-300" style="width: {{ max(0, min(100, round($rhVal))) }}%;"></div>
                                            <div class="scale-ticks pointer-events-none absolute inset-0"></div>
                                        </div>
                                        <div class="mt-0.5 flex justify-between font-mono text-[9px] text-zinc-600"><span>0</span><span>100%</span></div>
                                    </div>
                                </div>
                            </div>

                            {{-- 04 · TFT ILI9341 + Buzzer D4 --}}
                            <div id="indoor-bell-banner-{{ $device->id }}" class="{{ $base['card'] }} {{ $tone[$buzzer['tone']]['card'] }}">
                                <div class="flex items-center justify-between">
                                    <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500"><span class="text-zinc-600">04</span> · TFT + Buzzer D4</p>
                                    <span id="indoor-bell-led-{{ $device->id }}" class="{{ $base['led'] }} {{ $tone[$buzzer['tone']]['led'] }}"></span>
                                </div>
                                <div>
                                    <span id="indoor-bell-text-{{ $device->id }}" class="{{ $base['badge'] }} {{ $tone[$buzzer['tone']]['badge'] }}">{{ $buzzer['label'] }}</span>
                                </div>
                                <p id="indoor-bell-desc-{{ $device->id }}" class="text-sm font-medium text-zinc-100">{{ $buzzer['desc'] }}</p>
                                <div class="mt-auto border border-zinc-800 bg-black px-3 py-2">
                                    <p class="font-mono text-[9px] uppercase tracking-wider text-zinc-600">TFT ›</p>
                                    <p id="indoor-bell-sub-{{ $device->id }}" class="mt-0.5 break-words font-mono text-[11px] text-emerald-400">{{ $buzzer['tft'] }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Grafik indoor --}}
                        <div class="border-t border-zinc-800 px-4 pb-4 pt-3">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <p class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Trend · Gas MQ-2 &amp; Lingkungan</p>
                                <div class="flex items-center gap-4 font-mono text-[10px] text-zinc-400">
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-emerald-500"></span>Gas (ADC)</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-sky-400"></span>Suhu (°C)</span>
                                    <span class="inline-flex items-center gap-1.5"><span class="h-px w-3 border-t border-dashed border-zinc-400"></span>RH (%)</span>
                                </div>
                            </div>
                            <div class="relative h-56 border border-zinc-800 bg-zinc-950 p-2">
                                <canvas id="chart-indoor-{{ $device->id }}" aria-label="Grafik telemetri indoor" role="img"></canvas>
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        {{-- -------------------------------------------------------------- --}}
        {{-- P-02 · HARDWARE SIMULATOR                                        --}}
        {{-- -------------------------------------------------------------- --}}
        <aside class="xl:col-span-4">
            <section class="rounded-sm border border-zinc-800 bg-zinc-950 xl:sticky xl:top-20" aria-labelledby="simulator-title">
                <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-3">
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-[10px] text-zinc-600">P-02</span>
                        <h2 id="simulator-title" class="text-sm font-semibold text-zinc-100">Hardware Simulator</h2>
                    </div>
                    <span class="rounded-sm border border-amber-600 px-1.5 py-0.5 font-mono text-[9px] font-bold uppercase tracking-wider text-amber-400">Test Mode</span>
                </div>

                <p class="border-b border-zinc-800 px-5 py-2.5 text-xs leading-relaxed text-zinc-400">
                    Mengirim payload ke <span class="font-mono text-zinc-200">POST /api/telemetry</span> seolah-olah dari ESP32. Dashboard merespons pada siklus polling berikutnya.
                </p>

                <div class="divide-y divide-zinc-800">
                    @foreach ($devices as $simDevice)
                        <div class="px-5 py-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 {{ $simDevice->device_type === 'outdoor' ? 'bg-sky-500' : 'bg-emerald-500' }}"></span>
                                    <span class="truncate font-mono text-xs font-semibold text-zinc-200">{{ $simDevice->device_name }}</span>
                                </span>
                                <span class="shrink-0 font-mono text-[10px] uppercase tracking-wider text-zinc-500">{{ $simDevice->device_type }}</span>
                            </div>

                            @if ($simDevice->device_type === 'outdoor')
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="button"
                                        onclick="triggerHardwareSimulation(this, @js($simDevice->api_token), @js(['status_bel' => 1, 'sudut_solar' => 115, 'cahaya_timur' => 2150, 'cahaya_barat' => 2890]), @js('Bel 1x · Tamu'))"
                                        class="flex flex-col items-start gap-0.5 rounded-sm border border-zinc-700 bg-zinc-900 px-2.5 py-2 text-left transition-colors hover:border-emerald-500 disabled:cursor-wait disabled:opacity-50 active:translate-y-px">
                                        <span class="font-mono text-[11px] font-bold text-emerald-400">BEL 1x</span>
                                        <span class="font-mono text-[9px] text-zinc-500">status_bel=1</span>
                                    </button>
                                    <button type="button"
                                        onclick="triggerHardwareSimulation(this, @js($simDevice->api_token), @js(['status_bel' => 2, 'sudut_solar' => 125, 'cahaya_timur' => 1920, 'cahaya_barat' => 3180]), @js('Bel 3x · Kurir'))"
                                        class="flex flex-col items-start gap-0.5 rounded-sm border border-zinc-700 bg-zinc-900 px-2.5 py-2 text-left transition-colors hover:border-amber-500 disabled:cursor-wait disabled:opacity-50 active:translate-y-px">
                                        <span class="font-mono text-[11px] font-bold text-amber-400">BEL 3x</span>
                                        <span class="font-mono text-[9px] text-zinc-500">status_bel=2</span>
                                    </button>
                                    <button type="button"
                                        onclick="triggerHardwareSimulation(this, @js($simDevice->api_token), @js(['status_bel' => 0, 'sudut_solar' => 90, 'cahaya_timur' => 2650, 'cahaya_barat' => 2610]), @js('Reset · Standby'))"
                                        class="flex flex-col items-start gap-0.5 rounded-sm border border-zinc-700 bg-zinc-900 px-2.5 py-2 text-left transition-colors hover:border-zinc-400 disabled:cursor-wait disabled:opacity-50 active:translate-y-px">
                                        <span class="font-mono text-[11px] font-bold text-zinc-200">STANDBY</span>
                                        <span class="font-mono text-[9px] text-zinc-500">status_bel=0</span>
                                    </button>
                                </div>
                            @else
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="button"
                                        onclick="triggerHardwareSimulation(this, @js($simDevice->api_token), @js(['kadar_gas' => 800, 'suhu' => 28.5, 'kelembapan' => 63.0, 'sudut_servo_gas' => 20, 'status_bel' => 0]), @js('Gas Normal · 800 ADC'))"
                                        class="flex flex-col items-start gap-0.5 rounded-sm border border-zinc-700 bg-zinc-900 px-2.5 py-2 text-left transition-colors hover:border-emerald-500 disabled:cursor-wait disabled:opacity-50 active:translate-y-px">
                                        <span class="font-mono text-[11px] font-bold text-emerald-400">AMAN</span>
                                        <span class="font-mono text-[9px] text-zinc-500">gas=800</span>
                                    </button>
                                    <button type="button"
                                        onclick="triggerHardwareSimulation(this, @js($simDevice->api_token), @js(['kadar_gas' => 1850, 'suhu' => 31.4, 'kelembapan' => 58.0, 'sudut_servo_gas' => 90, 'status_bel' => 0]), @js('Gas Waspada · 1850 ADC'))"
                                        class="flex flex-col items-start gap-0.5 rounded-sm border border-zinc-700 bg-zinc-900 px-2.5 py-2 text-left transition-colors hover:border-amber-500 disabled:cursor-wait disabled:opacity-50 active:translate-y-px">
                                        <span class="font-mono text-[11px] font-bold text-amber-400">WASPADA</span>
                                        <span class="font-mono text-[9px] text-zinc-500">gas=1850</span>
                                    </button>
                                    <button type="button"
                                        onclick="triggerHardwareSimulation(this, @js($simDevice->api_token), @js(['kadar_gas' => 3400, 'suhu' => 42.0, 'kelembapan' => 46.5, 'sudut_servo_gas' => 160, 'status_bel' => 0]), @js('Gas Bocor · 3400 ADC'))"
                                        class="flex flex-col items-start gap-0.5 rounded-sm border border-red-800 bg-red-950/40 px-2.5 py-2 text-left transition-colors hover:border-red-500 hover:bg-red-950 disabled:cursor-wait disabled:opacity-50 active:translate-y-px">
                                        <span class="font-mono text-[11px] font-bold text-red-400">BOCOR</span>
                                        <span class="font-mono text-[9px] text-zinc-500">gas=3400</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Konsol transmisi --}}
                <div class="border-t border-zinc-800">
                    <div class="flex items-center justify-between px-5 py-2">
                        <span class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">TX Console</span>
                        <span id="simulator-feedback" class="font-mono text-[10px] text-zinc-500">IDLE</span>
                    </div>
                    <div id="simulator-log" class="h-44 overflow-y-auto border-t border-zinc-800 bg-black px-4 py-2 font-mono text-[11px] leading-relaxed text-zinc-400" aria-live="polite">
                        <div><span class="text-zinc-600">--:--:--</span> <span class="text-zinc-500">[SYS]</span> Siap. Pilih skenario untuk mengirim payload.</div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
    @endif

</div>
@endsection

@push('scripts')
{{-- Chart.js via CDN (versi dikunci agar stabil di hosting produksi) --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
    /* =========================================================================
     * KONFIGURASI
     * ========================================================================= */
    const BASE = @js($base);
    const TONE = @js($tone);
    const DEVICE_JSON_BASE = @js(url('/dashboard/device'));
    const API_TELEMETRY_URL = @js(url('/api/telemetry'));
    const LOGIN_URL = @js(route('login'));
    const POLL_INTERVAL_MS = 3000;
    const GAS_WARN = 1500;
    const GAS_DANGER = 3000;
    const LDR_DEADBAND = 120;
    const HAS_CHART = typeof window.Chart !== 'undefined';

    const deviceCharts = {};
    const chartHistoryKey = {};
    const deviceStateMap = {};
    let pollInFlight = false;
    let pollPending = false;

    /* =========================================================================
     * UTILITAS DOM (hanya menulis jika nilai berubah → tanpa kedip)
     * ========================================================================= */
    const byId = (id) => document.getElementById(id);
    const clamp = (n, min = 0, max = 100) => Math.min(max, Math.max(min, n));
    const pct = (value, max) => clamp(Math.round((value / max) * 100));

    function setText(id, value) {
        const el = byId(id);
        const str = String(value);
        if (el && el.textContent !== str) el.textContent = str;
    }

    function setClass(id, cls) {
        const el = byId(id);
        if (el && el.className !== cls) el.className = cls;
    }

    function setWidth(id, percent) {
        const el = byId(id);
        const w = clamp(percent) + '%';
        if (el && el.style.width !== w) el.style.width = w;
    }

    function setNeedle(id, angle) {
        const el = byId(id);
        if (el) el.style.transform = 'rotate(' + (clamp(angle, 0, 180) - 90) + 'deg)';
    }

    const toneClass = (part, tone) => BASE[part] + ' ' + TONE[tone][part];

    /* =========================================================================
     * LOGIKA STATUS (cermin dari @php di atas)
     * ========================================================================= */
    function gasState(gas) {
        if (gas >= GAS_DANGER) return { tone: 'danger', label: 'BAHAYA · SIRINE AKTIF' };
        if (gas >= GAS_WARN) return { tone: 'warn', label: 'WASPADA' };
        return { tone: 'ok', label: 'AMAN' };
    }

    function servoZoneState(angle) {
        if (angle >= 120) return { tone: 'danger', label: 'ZONA MERAH' };
        if (angle >= 60) return { tone: 'warn', label: 'ZONA KUNING' };
        return { tone: 'ok', label: 'ZONA HIJAU' };
    }

    function outdoorBellState(bel) {
        if (bel === 1) return { tone: 'active', label: 'TAMU DATANG', desc: '1x klik · Silakan masuk', sub: 'ESP-NOW → Indoor: nada TING-TUNG' };
        if (bel === 2) return { tone: 'warn', label: 'KURIR PAKET', desc: '3x klik · Taruh di gerbang', sub: 'ESP-NOW → Indoor: BEEP 3x' };
        return { tone: 'idle', label: 'STANDBY', desc: 'Menunggu input tombol KY-004', sub: 'Tidak ada panggilan aktif' };
    }

    function indoorBellState(gas, bel, suhu, rh) {
        if (gas >= GAS_DANGER) return { tone: 'danger', label: 'SIRINE DARURAT', desc: 'Buzzer D4 · alarm kontinu', tft: '!! BAHAYA GAS BOCOR !! EVAKUASI SEGERA' };
        if (bel === 1) return { tone: 'active', label: 'TING-TUNG', desc: 'Buzzer D4 · nada tamu', tft: 'TAMU DATANG - SILAKAN MASUK' };
        if (bel === 2) return { tone: 'warn', label: 'BEEP 3x', desc: 'Buzzer D4 · nada kurir', tft: 'KURIR PAKET - TARUH DI GERBANG' };
        return { tone: 'idle', label: 'STANDBY', desc: 'Buzzer D4 · senyap', tft: 'T ' + suhu.toFixed(1) + 'C  RH ' + rh.toFixed(1) + '%  GAS ' + gas };
    }

    function solarDirection(timur, barat) {
        const delta = timur - barat;
        if (Math.abs(delta) <= LDR_DEADBAND) return 'SEIMBANG';
        return delta > 0 ? 'CONDONG TIMUR' : 'CONDONG BARAT';
    }

    function liveOutdoorBell() {
        let bell = 0;
        Object.values(deviceStateMap).forEach((st) => {
            if (st.type === 'outdoor' && st.latest) {
                bell = Math.max(bell, parseInt(st.latest.status_bel ?? 0, 10));
            }
        });
        return bell;
    }

    /* =========================================================================
     * FORM PAIRING
     * ========================================================================= */
    function quickFillDevice(type) {
        const nameInput = byId('input-device-name');
        const passInput = byId('input-device-password');
        const roomInput = byId('input-nama-ruangan');
        if (!nameInput || !passInput || !roomInput) return;

        if (type === 'outdoor') {
            nameInput.value = 'IOTR-OUTDOOR-01';
            passInput.value = 'OUTDOOR123';
            roomInput.value = 'Gerbang Utama';
        } else {
            nameInput.value = 'IOTR-INDOOR-01';
            passInput.value = 'INDOOR123';
            roomInput.value = 'Dapur & Ruang Keluarga';
        }
        roomInput.focus();
        roomInput.select();
    }

    function toggleDevicePassword() {
        const input = byId('input-device-password');
        const btn = byId('toggle-device-password');
        if (!input || !btn) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'Hide' : 'Show';
        btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
    }

    /* =========================================================================
     * CHART.JS — TEMA INDUSTRIAL
     * ========================================================================= */
    const gasColor = (v) => (v >= GAS_DANGER ? '#dc2626' : v >= GAS_WARN ? '#f59e0b' : '#10b981');

    const thresholdLinesPlugin = {
        id: 'thresholdLines',
        afterDatasetsDraw(chart, args, opts) {
            if (!opts || !opts.lines || !chart.scales[opts.axis]) return;
            const scale = chart.scales[opts.axis];
            const { ctx, chartArea } = chart;
            ctx.save();
            opts.lines.forEach((line) => {
                const y = scale.getPixelForValue(line.value);
                ctx.strokeStyle = line.color;
                ctx.lineWidth = 1;
                ctx.setLineDash([4, 4]);
                ctx.beginPath();
                ctx.moveTo(chartArea.left, y);
                ctx.lineTo(chartArea.right, y);
                ctx.stroke();
                ctx.setLineDash([]);
                ctx.fillStyle = line.color;
                ctx.font = "10px 'JetBrains Mono', monospace";
                ctx.fillText(line.label, chartArea.left + 4, y - 4);
            });
            ctx.restore();
        },
    };

    if (HAS_CHART) {
        Chart.defaults.color = '#71717a';
        Chart.defaults.borderColor = '#27272a';
        Chart.defaults.font.family = "'JetBrains Mono', monospace";
        Chart.defaults.font.size = 10;
    }

    function lineDataset(label, color, axis, extra) {
        return Object.assign({
            label: label,
            data: [],
            borderColor: color,
            backgroundColor: color,
            borderWidth: 1.5,
            pointRadius: 2.5,
            pointHoverRadius: 4,
            pointBackgroundColor: '#09090b',
            pointBorderColor: color,
            pointBorderWidth: 1.5,
            tension: 0,
            fill: false,
            yAxisID: axis,
        }, extra || {});
    }

    function chartOptions(scales, pluginOpts) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            plugins: Object.assign({
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#09090b',
                    borderColor: '#3f3f46',
                    borderWidth: 1,
                    cornerRadius: 2,
                    padding: 8,
                    titleColor: '#e4e4e7',
                    bodyColor: '#a1a1aa',
                    boxWidth: 8,
                    boxHeight: 8,
                    titleFont: { family: "'JetBrains Mono', monospace", size: 11 },
                    bodyFont: { family: "'JetBrains Mono', monospace", size: 11 },
                },
            }, pluginOpts || {}),
            scales: Object.assign({
                x: {
                    grid: { color: '#18181b' },
                    border: { color: '#3f3f46' },
                    ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                },
            }, scales),
        };
    }

    function initDeviceChart(deviceId, deviceType) {
        if (!HAS_CHART) return;

        if (deviceType === 'outdoor') {
            const canvas = byId('chart-outdoor-' + deviceId);
            if (!canvas) return;
            deviceCharts[deviceId] = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        lineDataset('LDR Timur', '#f59e0b', 'yAdc'),
                        lineDataset('LDR Barat', '#38bdf8', 'yAdc'),
                        lineDataset('Servo Solar (°)', '#e4e4e7', 'yAngle', { borderDash: [4, 3], pointRadius: 2 }),
                    ],
                },
                options: chartOptions({
                    yAdc: {
                        position: 'left', min: 0, max: 4095,
                        grid: { color: '#18181b' }, border: { color: '#3f3f46' },
                        ticks: { stepSize: 1024 },
                        title: { display: true, text: 'ADC', color: '#71717a' },
                    },
                    yAngle: {
                        position: 'right', min: 0, max: 180,
                        grid: { drawOnChartArea: false }, border: { color: '#3f3f46' },
                        ticks: { stepSize: 45 },
                        title: { display: true, text: 'DEG', color: '#71717a' },
                    },
                }),
            });
        } else {
            const canvas = byId('chart-indoor-' + deviceId);
            if (!canvas) return;
            deviceCharts[deviceId] = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        lineDataset('Gas MQ-2 (ADC)', '#10b981', 'yGas', {
                            borderWidth: 1.75,
                            segment: {
                                borderColor: (ctx) => gasColor(Math.max(ctx.p0.parsed.y, ctx.p1.parsed.y)),
                            },
                            pointBorderColor: (ctx) => gasColor(ctx.raw ?? 0),
                        }),
                        lineDataset('Suhu (°C)', '#38bdf8', 'yEnv'),
                        lineDataset('Kelembapan (%)', '#a1a1aa', 'yEnv', { borderDash: [4, 3], pointRadius: 2 }),
                    ],
                },
                options: chartOptions({
                    yGas: {
                        position: 'left', min: 0, max: 4095,
                        grid: { color: '#18181b' }, border: { color: '#3f3f46' },
                        ticks: { stepSize: 1000 },
                        title: { display: true, text: 'ADC', color: '#71717a' },
                    },
                    yEnv: {
                        position: 'right', min: 0, max: 100,
                        grid: { drawOnChartArea: false }, border: { color: '#3f3f46' },
                        ticks: { stepSize: 25 },
                        title: { display: true, text: '°C / %RH', color: '#71717a' },
                    },
                }, {
                    thresholdLines: {
                        axis: 'yGas',
                        lines: [
                            { value: GAS_WARN, color: 'rgba(245, 158, 11, 0.7)', label: 'WASPADA 1500' },
                            { value: GAS_DANGER, color: 'rgba(220, 38, 38, 0.8)', label: 'BAHAYA 3000' },
                        ],
                    },
                }),
                plugins: [thresholdLinesPlugin],
            });
        }
    }

    function updateChart(deviceId, deviceType, history) {
        const chart = deviceCharts[deviceId];
        if (!chart) return;

        // Hanya redraw jika ada sampel baru → grafik tidak berkedip
        const key = history.map((h) => h.id).join(',');
        if (chartHistoryKey[deviceId] === key) return;
        chartHistoryKey[deviceId] = key;

        chart.data.labels = history.map((h) => h.time_label);
        if (deviceType === 'outdoor') {
            chart.data.datasets[0].data = history.map((h) => h.cahaya_timur ?? 0);
            chart.data.datasets[1].data = history.map((h) => h.cahaya_barat ?? 0);
            chart.data.datasets[2].data = history.map((h) => h.sudut_solar ?? 90);
        } else {
            chart.data.datasets[0].data = history.map((h) => h.kadar_gas ?? 0);
            chart.data.datasets[1].data = history.map((h) => h.suhu ?? 0);
            chart.data.datasets[2].data = history.map((h) => h.kelembapan ?? 0);
        }
        chart.update('none');
    }

    /* =========================================================================
     * RENDER PANEL PERANGKAT
     * ========================================================================= */
    function renderLinkStatus(deviceId, payload) {
        setText('last-seen-' + deviceId, payload.last_seen_human);

        const badge = byId('status-badge-' + deviceId);
        if (!badge) return;
        const online = !!payload.is_online;
        const cls = 'inline-flex items-center gap-1.5 font-bold ' + (online ? 'text-emerald-400' : 'text-red-400');
        if (badge.className !== cls || badge.dataset.online !== String(online)) {
            badge.className = cls;
            badge.dataset.online = String(online);
            badge.innerHTML = '<span class="' + BASE.led + ' ' + (online ? 'bg-emerald-500' : 'bg-red-500') + '"></span>' + (online ? 'ONLINE' : 'OFFLINE');
        }
    }

    function renderOutdoor(id, latest) {
        const bel = parseInt(latest.status_bel ?? 0, 10);
        const servo = parseInt(latest.sudut_solar ?? 90, 10);
        const timur = parseInt(latest.cahaya_timur ?? 0, 10);
        const barat = parseInt(latest.cahaya_barat ?? 0, 10);
        const bell = outdoorBellState(bel);
        const delta = timur - barat;

        setClass('outdoor-bel-card-' + id, toneClass('card', bell.tone));
        setClass('outdoor-bel-led-' + id, toneClass('led', bell.tone));
        setClass('outdoor-bel-text-' + id, toneClass('badge', bell.tone));
        setText('outdoor-bel-text-' + id, bell.label);
        setText('outdoor-bel-desc-' + id, bell.desc);
        setText('outdoor-bel-sub-' + id, bell.sub);
        setText('outdoor-bel-code-' + id, bel);

        setText('outdoor-servo-' + id, servo + '°');
        setNeedle('outdoor-servo-needle-' + id, servo);
        setText('outdoor-solar-dir-' + id, solarDirection(timur, barat));

        setText('outdoor-ldr-timur-' + id, timur);
        setWidth('outdoor-ldr-timur-bar-' + id, pct(timur, 4095));
        setText('outdoor-ldr-barat-' + id, barat);
        setWidth('outdoor-ldr-barat-bar-' + id, pct(barat, 4095));
        setText('outdoor-ldr-delta-' + id, (delta > 0 ? '+' : '') + delta);
    }

    function renderIndoor(id, latest) {
        const gas = parseInt(latest.kadar_gas ?? 0, 10);
        const servo = parseInt(latest.sudut_servo_gas ?? 20, 10);
        const suhu = parseFloat(latest.suhu ?? 0);
        const rh = parseFloat(latest.kelembapan ?? 0);
        const bel = Math.max(parseInt(latest.status_bel ?? 0, 10), liveOutdoorBell());

        const g = gasState(gas);
        setClass('indoor-gas-card-' + id, toneClass('card', g.tone));
        setClass('indoor-gas-led-' + id, toneClass('led', g.tone));
        setClass('indoor-gas-status-' + id, toneClass('badge', g.tone));
        setText('indoor-gas-status-' + id, g.label);
        setText('indoor-gas-val-' + id, gas);
        setClass('indoor-gas-bar-' + id, toneClass('bar', g.tone));
        setWidth('indoor-gas-bar-' + id, pct(gas, 4095));

        const z = servoZoneState(servo);
        setText('indoor-servo-gas-' + id, servo + '°');
        setNeedle('indoor-servo-gas-needle-' + id, servo);
        setClass('indoor-servo-zone-' + id, toneClass('text', z.tone));
        setText('indoor-servo-zone-' + id, z.label);

        setText('indoor-suhu-' + id, suhu.toFixed(1) + '°C');
        setWidth('indoor-suhu-bar-' + id, pct(suhu, 50));
        setText('indoor-kelembapan-' + id, rh.toFixed(1) + '%');
        setWidth('indoor-kelembapan-bar-' + id, pct(rh, 100));

        const b = indoorBellState(gas, bel, suhu, rh);
        setClass('indoor-bell-banner-' + id, toneClass('card', b.tone));
        setClass('indoor-bell-led-' + id, toneClass('led', b.tone));
        setClass('indoor-bell-text-' + id, toneClass('badge', b.tone));
        setText('indoor-bell-text-' + id, b.label);
        setText('indoor-bell-desc-' + id, b.desc);
        setText('indoor-bell-sub-' + id, b.tft);
    }

    function renderDevice(payload) {
        const device = payload.device;
        renderLinkStatus(device.id, payload);

        if (payload.latest) {
            if (device.device_type === 'outdoor') {
                renderOutdoor(device.id, payload.latest);
            } else {
                renderIndoor(device.id, payload.latest);
            }
        }
        updateChart(device.id, device.device_type, payload.history || []);
    }

    /* =========================================================================
     * STATUS GLOBAL (banner alarm, badge keamanan, jumlah online)
     * ========================================================================= */
    function renderGlobalState() {
        let dangerDevice = null;
        let anyWarn = false;
        let maxBell = 0;
        let online = 0;

        Object.values(deviceStateMap).forEach((st) => {
            if (st.is_online) online++;
            if (!st.latest) return;
            const gas = parseInt(st.latest.kadar_gas ?? 0, 10);
            const bel = parseInt(st.latest.status_bel ?? 0, 10);
            if (st.type === 'indoor' && gas >= GAS_DANGER && !dangerDevice) dangerDevice = { device: st.device, gas: gas };
            if (st.type === 'indoor' && gas >= GAS_WARN && gas < GAS_DANGER) anyWarn = true;
            maxBell = Math.max(maxBell, bel);
        });

        setText('stat-online-count', online);

        const banner = byId('emergency-banner');
        if (banner) {
            banner.classList.toggle('hidden', !dangerDevice);
            banner.classList.toggle('flex', !!dangerDevice);
        }
        if (dangerDevice) {
            const name = dangerDevice.device.nama_ruangan || 'Unit indoor';
            setText('emergency-banner-desc',
                name + ' (' + dangerDevice.device.device_name + ') membaca ' + dangerDevice.gas +
                ' ADC. Jarum servo di zona merah dan sirine buzzer D4 aktif. Segera buka ventilasi dan putus sumber gas.');
        }

        let state;
        if (dangerDevice) state = { tone: 'danger', label: 'BAHAYA · GAS BOCOR' };
        else if (anyWarn) state = { tone: 'warn', label: 'WASPADA · GAS NAIK' };
        else if (maxBell === 1) state = { tone: 'active', label: 'TAMU DI GERBANG' };
        else if (maxBell === 2) state = { tone: 'warn', label: 'KURIR PAKET' };
        else state = { tone: 'ok', label: 'AMAN · TERKENDALI' };

        setClass('global-security-badge', toneClass('badge', state.tone));
        setText('global-security-badge', state.label);
    }

    function setSyncIndicator(status) {
        const time = new Date().toLocaleTimeString('id-ID', { hour12: false });
        const map = {
            ok:      { dot: 'bg-emerald-500', text: 'LINK OK · ' + time },
            partial: { dot: 'bg-amber-400',   text: 'PARSIAL · ' + time },
            lost:    { dot: 'bg-red-500 blink', text: 'LINK LOST' },
        };
        const s = map[status];
        setClass('global-sync-dot', 'inline-block h-2 w-2 shrink-0 rounded-full ' + s.dot);
        setText('global-sync-text', s.text);
    }

    /* =========================================================================
     * MESIN POLLING REAL-TIME (3 detik)
     * ========================================================================= */
    async function fetchDevice(deviceId) {
        const res = await fetch(DEVICE_JSON_BASE + '/' + encodeURIComponent(deviceId) + '/json', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (res.status === 401 || res.status === 419) {
            window.location.href = LOGIN_URL;
            throw new Error('Sesi berakhir');
        }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    async function pollAllDevices() {
        if (pollInFlight) {
            pollPending = true;
            return;
        }
        pollInFlight = true;

        try {
            const ids = Array.from(document.querySelectorAll('[data-device-id]')).map((el) => el.dataset.deviceId);
            if (ids.length === 0) return;

            const results = await Promise.allSettled(ids.map(fetchDevice));
            const payloads = results.filter((r) => r.status === 'fulfilled').map((r) => r.value);

            // Simpan state semua unit dulu agar indoor membaca status bel outdoor terbaru
            payloads.forEach((p) => {
                deviceStateMap[p.device.id] = {
                    type: p.device.device_type,
                    device: p.device,
                    latest: p.latest,
                    is_online: p.is_online,
                };
            });

            payloads
                .sort((a, b) => (a.device.device_type === 'outdoor' ? 0 : 1) - (b.device.device_type === 'outdoor' ? 0 : 1))
                .forEach(renderDevice);

            renderGlobalState();
            setSyncIndicator(payloads.length === ids.length ? 'ok' : (payloads.length > 0 ? 'partial' : 'lost'));
        } catch (err) {
            console.error('[IoTR] Polling gagal:', err);
            setSyncIndicator('lost');
        } finally {
            pollInFlight = false;
            if (pollPending) {
                pollPending = false;
                pollAllDevices();
            }
        }
    }

    /* =========================================================================
     * HARDWARE SIMULATOR (POST /api/telemetry)
     * ========================================================================= */
    function logSim(level, message) {
        const log = byId('simulator-log');
        if (!log) return;

        const colors = { TX: 'text-sky-400', OK: 'text-emerald-400', ERR: 'text-red-400', SYS: 'text-zinc-500' };
        const row = document.createElement('div');
        const time = document.createElement('span');
        const tag = document.createElement('span');
        const msg = document.createElement('span');

        time.className = 'text-zinc-600';
        time.textContent = new Date().toLocaleTimeString('id-ID', { hour12: false }) + ' ';
        tag.className = colors[level] || 'text-zinc-500';
        tag.textContent = '[' + level + '] ';
        msg.textContent = message;

        row.append(time, tag, msg);
        log.prepend(row);
        while (log.children.length > 30) log.lastElementChild.remove();
    }

    async function triggerHardwareSimulation(button, apiToken, payload, label) {
        if (button) button.disabled = true;
        setText('simulator-feedback', 'SENDING…');
        logSim('TX', label + ' → ' + JSON.stringify(payload));

        try {
            const res = await fetch(API_TELEMETRY_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + apiToken,
                },
                body: JSON.stringify(payload),
            });

            if (res.ok) {
                logSim('OK', 'HTTP ' + res.status + ' · ' + label);
                setText('simulator-feedback', 'HTTP ' + res.status);
                await pollAllDevices();
            } else {
                logSim('ERR', 'HTTP ' + res.status + ' · payload ditolak server');
                setText('simulator-feedback', 'HTTP ' + res.status);
            }
        } catch (err) {
            logSim('ERR', err.message);
            setText('simulator-feedback', 'ERROR');
        } finally {
            if (button) button.disabled = false;
        }
    }

    /* =========================================================================
     * BOOT
     * ========================================================================= */
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-device-id]').forEach((panel) => {
            initDeviceChart(panel.dataset.deviceId, panel.dataset.deviceType);
        });

        if (!HAS_CHART) {
            logSim('ERR', 'Chart.js gagal dimuat dari CDN. Grafik dinonaktifkan, metrik tetap live.');
        }

        if (document.querySelector('[data-device-id]')) {
            pollAllDevices();
            setInterval(pollAllDevices, POLL_INTERVAL_MS);
        }
    });
</script>
@endpush
