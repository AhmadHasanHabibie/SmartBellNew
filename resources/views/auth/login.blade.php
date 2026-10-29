@extends('layouts.app')

@section('title', 'Masuk — IoTR // NEXUS')

@section('content')
<div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-12 lg:gap-12 lg:px-8 lg:py-16">

    {{-- ============================================================== --}}
    {{-- KOLOM KIRI — LEMBAR SPESIFIKASI SISTEM                           --}}
    {{-- ============================================================== --}}
    <section class="order-2 lg:order-1 lg:col-span-7">
        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-emerald-500">System Access</p>
        <h1 class="mt-3 max-w-xl text-3xl font-bold tracking-tight text-zinc-100 sm:text-4xl">
            Pusat kendali Smart Bell &amp; Gas Mechatronics.
        </h1>
        <p class="mt-4 max-w-xl text-sm leading-relaxed text-zinc-400">
            Pantau unit outdoor (bel KY-004, solar tracker) dan unit indoor (MQ-2, DHT22, gauge servo, TFT)
            dari satu panel. ESP32 mengirim telemetri lewat HTTP, sedangkan panggilan bel diteruskan
            antar-unit secara lokal melalui ESP-NOW.
        </p>

        <div class="mt-8 rounded-sm border border-zinc-800">
            <div class="flex items-center justify-between border-b border-zinc-800 bg-zinc-950 px-4 py-2">
                <span class="font-mono text-[10px] uppercase tracking-[0.18em] text-zinc-400">Spesifikasi Sistem</span>
                <span class="font-mono text-[10px] text-zinc-600">REV 1.0</span>
            </div>
            <dl class="divide-y divide-zinc-800 font-mono text-xs">
                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                    <dt class="text-zinc-500">NODE A</dt>
                    <dd class="col-span-2 text-zinc-200">Outdoor · KY-004 · LDR ×2 · Servo SG90</dd>
                </div>
                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                    <dt class="text-zinc-500">NODE B</dt>
                    <dd class="col-span-2 text-zinc-200">Indoor · MQ-2 · DHT22 · Servo D13 · TFT · Buzzer D4</dd>
                </div>
                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                    <dt class="text-zinc-500">LINK</dt>
                    <dd class="col-span-2 text-zinc-200">ESP-NOW (lokal) + HTTP POST (cloud)</dd>
                </div>
                <div class="grid grid-cols-3 gap-4 px-4 py-2.5">
                    <dt class="text-zinc-500">AMBANG GAS</dt>
                    <dd class="col-span-2 text-zinc-200">
                        <span class="text-emerald-400">&lt;1500</span> /
                        <span class="text-amber-400">1500–2999</span> /
                        <span class="text-red-400">&ge;3000</span> ADC
                    </dd>
                </div>
            </dl>
        </div>

        <div class="mt-6">
            @include('partials.device-plate')
        </div>
    </section>

    {{-- ============================================================== --}}
    {{-- KOLOM KANAN — FORM LOGIN                                         --}}
    {{-- ============================================================== --}}
    <section class="order-1 lg:order-2 lg:col-span-5">
        <div class="rounded-sm border border-zinc-800 bg-zinc-950">
            <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-3">
                <h2 class="text-sm font-semibold text-zinc-100">Masuk ke Konsol</h2>
                <span class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Auth / 01</span>
            </div>

            <div class="p-5">
                @if (session('success'))
                    <div class="mb-4 border-l-2 border-emerald-500 bg-zinc-900 px-3 py-2 text-xs text-zinc-200">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 border-l-2 border-red-600 bg-red-950/40 px-3 py-2 text-xs text-red-200" role="alert">
                        <p class="font-mono text-[10px] font-bold uppercase tracking-wider text-red-400">Otorisasi Gagal</p>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('login.submit') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-zinc-400">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                            placeholder="operator@domain.id"
                            class="h-10 w-full rounded-sm border bg-zinc-900 px-3 font-mono text-sm text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 @error('email') border-red-600 @else border-zinc-700 @enderror">
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-zinc-400">Kata Sandi</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            placeholder="••••••••"
                            class="h-10 w-full rounded-sm border bg-zinc-900 px-3 font-mono text-sm text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 @error('password') border-red-600 @else border-zinc-700 @enderror">
                    </div>

                    <label class="flex cursor-pointer select-none items-center gap-2 text-xs text-zinc-400">
                        <input type="checkbox" name="remember" class="h-3.5 w-3.5 rounded-sm border-zinc-600 bg-zinc-900 accent-emerald-600">
                        Pertahankan sesi
                    </label>

                    <button type="submit"
                        class="h-10 w-full rounded-sm bg-emerald-600 font-mono text-xs font-bold uppercase tracking-wider text-zinc-950 transition-colors hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 active:bg-emerald-700">
                        Masuk
                    </button>
                </form>
            </div>

            <div class="border-t border-zinc-800 px-5 py-3 text-xs text-zinc-500">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-semibold text-zinc-200 underline decoration-zinc-600 underline-offset-4 hover:decoration-emerald-500">Daftar akun pemilik</a>
            </div>
        </div>
    </section>
</div>
@endsection
