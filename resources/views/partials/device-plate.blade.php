{{--
    Pelat data perangkat pabrikan (dipakai di Login, Register, dan Empty State Dashboard).
    Menampilkan S/N dan password klaim yang tercetak di bodi belakang unit ESP32.
--}}
<div class="relative rounded-sm border border-zinc-700 bg-zinc-900">
    {{-- Baut sudut pelat --}}
    <span class="absolute left-1.5 top-1.5 h-1.5 w-1.5 rounded-full border border-zinc-600 bg-zinc-800" aria-hidden="true"></span>
    <span class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full border border-zinc-600 bg-zinc-800" aria-hidden="true"></span>
    <span class="absolute bottom-1.5 left-1.5 h-1.5 w-1.5 rounded-full border border-zinc-600 bg-zinc-800" aria-hidden="true"></span>
    <span class="absolute bottom-1.5 right-1.5 h-1.5 w-1.5 rounded-full border border-zinc-600 bg-zinc-800" aria-hidden="true"></span>

    <div class="flex items-center justify-between border-b border-zinc-700 px-5 py-2.5">
        <span class="font-mono text-[10px] font-bold uppercase tracking-[0.18em] text-zinc-300">Info Perangkat Pabrikan</span>
        <span class="inline-flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-wider text-emerald-400">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            Siap Klaim
        </span>
    </div>

    <div class="grid grid-cols-1 divide-y divide-zinc-700 sm:grid-cols-2 sm:divide-x sm:divide-y-0">
        <div class="px-5 py-3 font-mono text-xs">
            <p class="text-[10px] uppercase tracking-wider text-sky-400">Node A &middot; Outdoor</p>
            <dl class="mt-2 space-y-1">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-[10px] text-zinc-500">S/N</dt>
                    <dd class="font-semibold text-zinc-100">IOTR-OUTDOOR-01</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-[10px] text-zinc-500">PASS</dt>
                    <dd class="font-semibold text-zinc-100">OUTDOOR123</dd>
                </div>
            </dl>
        </div>
        <div class="px-5 py-3 font-mono text-xs">
            <p class="text-[10px] uppercase tracking-wider text-emerald-400">Node B &middot; Indoor</p>
            <dl class="mt-2 space-y-1">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-[10px] text-zinc-500">S/N</dt>
                    <dd class="font-semibold text-zinc-100">IOTR-INDOOR-01</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-[10px] text-zinc-500">PASS</dt>
                    <dd class="font-semibold text-zinc-100">INDOOR123</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
