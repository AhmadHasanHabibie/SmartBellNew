@extends('layouts.app')

@section('title', 'Registrasi — IoTR // NEXUS')

@section('content')
<div class="mx-auto grid max-w-6xl gap-6 sm:gap-8 px-3.5 py-6 sm:px-6 sm:py-10 lg:grid-cols-12 lg:gap-12 lg:px-8 lg:py-16">

    {{-- ============================================================== --}}
    {{-- KOLOM KIRI — ALUR ONBOARDING                                     --}}
    {{-- ============================================================== --}}
    <section class="order-2 lg:order-1 lg:col-span-7">
        <p class="font-mono text-[10px] sm:text-[11px] uppercase tracking-[0.2em] text-emerald-500">Owner Onboarding</p>
        <h1 class="mt-2 sm:mt-3 max-w-xl text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight text-zinc-100">
            Daftarkan akun, lalu klaim unit Anda.
        </h1>
        <p class="mt-2.5 sm:mt-4 max-w-xl text-xs sm:text-sm leading-relaxed text-zinc-400">
            Unit ESP32 sudah terdaftar dari pabrik. Setelah akun dibuat, hubungkan setiap unit cukup dengan
            S/N dan password yang tertera pada pelat data di bodi belakang alat.
        </p>

        <ol class="mt-6 sm:mt-8 divide-y divide-zinc-800 rounded-sm border border-zinc-800 font-mono text-[11px] sm:text-xs">
            <li class="grid grid-cols-12 gap-2 sm:gap-4 px-3.5 sm:px-4 py-2.5 sm:py-3">
                <span class="col-span-2 text-zinc-500">01</span>
                <span class="col-span-10 text-zinc-200">Buat akun pemilik perangkat</span>
            </li>
            <li class="grid grid-cols-12 gap-2 sm:gap-4 px-3.5 sm:px-4 py-2.5 sm:py-3">
                <span class="col-span-2 text-zinc-500">02</span>
                <span class="col-span-10 text-zinc-200">Masukkan S/N + password pelat data di panel Pairing</span>
            </li>
            <li class="grid grid-cols-12 gap-2 sm:gap-4 px-3.5 sm:px-4 py-2.5 sm:py-3">
                <span class="col-span-2 text-zinc-500">03</span>
                <span class="col-span-10 text-zinc-200">Telemetri unit tampil otomatis di dashboard</span>
            </li>
        </ol>

        <div class="mt-5 sm:mt-6">
            @include('partials.device-plate')
        </div>
    </section>

    {{-- ============================================================== --}}
    {{-- KOLOM KANAN — FORM REGISTRASI                                    --}}
    {{-- ============================================================== --}}
    <section class="order-1 lg:order-2 lg:col-span-5">
        <div class="rounded-sm border border-zinc-800 bg-zinc-950">
            <div class="flex items-center justify-between border-b border-zinc-800 px-4 sm:px-5 py-2.5 sm:py-3">
                <h2 class="text-sm font-semibold text-zinc-100">Registrasi Akun Pemilik</h2>
                <span class="font-mono text-[10px] uppercase tracking-wider text-zinc-500">Auth / 02</span>
            </div>

            <div class="p-4 sm:p-5">
                @if ($errors->any())
                    <div class="mb-4 border-l-2 border-red-600 bg-red-950/40 px-3 py-2 text-xs text-red-200" role="alert">
                        <p class="font-mono text-[10px] font-bold uppercase tracking-wider text-red-400">Validasi Gagal</p>
                        <ul class="mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('register.submit') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="mb-1 sm:mb-1.5 block font-mono text-[9px] sm:text-[10px] uppercase tracking-wider text-zinc-400">Nama Lengkap</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                            placeholder="Nama pemilik / operator"
                            class="h-10 w-full rounded-sm border bg-zinc-900 px-3 text-sm text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 @error('name') border-red-600 @else border-zinc-700 @enderror">
                    </div>

                    <div>
                        <label for="email" class="mb-1 sm:mb-1.5 block font-mono text-[9px] sm:text-[10px] uppercase tracking-wider text-zinc-400">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                            placeholder="operator@domain.id"
                            class="h-10 w-full rounded-sm border bg-zinc-900 px-3 font-mono text-sm text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 @error('email') border-red-600 @else border-zinc-700 @enderror">
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password" class="mb-1 sm:mb-1.5 block font-mono text-[9px] sm:text-[10px] uppercase tracking-wider text-zinc-400">Kata Sandi</label>
                            <input type="password" id="password" name="password" required autocomplete="new-password"
                                placeholder="Min. 6 karakter"
                                class="h-10 w-full rounded-sm border bg-zinc-900 px-3 font-mono text-sm text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 @error('password') border-red-600 @else border-zinc-700 @enderror">
                        </div>
                        <div>
                            <label for="password_confirmation" class="mb-1 sm:mb-1.5 block font-mono text-[9px] sm:text-[10px] uppercase tracking-wider text-zinc-400">Konfirmasi</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                                placeholder="Ulangi sandi"
                                class="h-10 w-full rounded-sm border border-zinc-700 bg-zinc-900 px-3 font-mono text-sm text-zinc-100 placeholder-zinc-600 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>

                    <button type="submit"
                        class="h-10 w-full rounded-sm bg-emerald-600 font-mono text-xs font-bold uppercase tracking-wider text-zinc-950 transition-colors hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 active:bg-emerald-700">
                        Buat Akun &amp; Buka Dashboard
                    </button>
                </form>
            </div>

            <div class="border-t border-zinc-800 px-4 sm:px-5 py-3 text-xs text-zinc-500">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-zinc-200 underline decoration-zinc-600 underline-offset-4 hover:decoration-emerald-500">Masuk</a>
            </div>
        </div>
    </section>
</div>
@endsection
