{{--
    Gauge analog setengah lingkaran (0° kiri — 180° kanan).

    Parameter:
      $needleId   : ID elemen jarum (diputar oleh JavaScript polling)
      $angle      : sudut awal 0–180
      $variant    : 'zones' (hijau/kuning/merah) atau 'neutral'
      $leftLabel  : label kiri  (default '0°')
      $rightLabel : label kanan (default '180°')
      $label      : aria-label
--}}
@php
    $variant = $variant ?? 'neutral';
    $leftLabel = $leftLabel ?? '0°';
    $rightLabel = $rightLabel ?? '180°';
    $rotation = max(0, min(180, (int) $angle)) - 90;

    $ticks = [];
    foreach ([0, 30, 60, 90, 120, 150, 180] as $deg) {
        $rad = deg2rad($deg);
        $ticks[] = [
            'x1' => round(100 - 62 * cos($rad), 2),
            'y1' => round(100 - 62 * sin($rad), 2),
            'x2' => round(100 - 70 * cos($rad), 2),
            'y2' => round(100 - 70 * sin($rad), 2),
        ];
    }
@endphp
<svg viewBox="0 0 200 118" class="w-full max-w-[180px] sm:max-w-[210px]" role="img" aria-label="{{ $label ?? 'Gauge' }}">
    <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke="#27272a" stroke-width="10"/>

    @if ($variant === 'zones')
        <path d="M20 100 A80 80 0 0 1 60 30.72" fill="none" stroke="#10b981" stroke-width="10"/>
        <path d="M60 30.72 A80 80 0 0 1 140 30.72" fill="none" stroke="#f59e0b" stroke-width="10"/>
        <path d="M140 30.72 A80 80 0 0 1 180 100" fill="none" stroke="#dc2626" stroke-width="10"/>
    @else
        <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke="#52525b" stroke-width="2"/>
    @endif

    @foreach ($ticks as $tick)
        <line x1="{{ $tick['x1'] }}" y1="{{ $tick['y1'] }}" x2="{{ $tick['x2'] }}" y2="{{ $tick['y2'] }}" stroke="#52525b" stroke-width="1.5"/>
    @endforeach

    <text x="20" y="115" fill="#71717a" font-family="JetBrains Mono, monospace" font-size="9" text-anchor="middle">{{ $leftLabel }}</text>
    <text x="180" y="115" fill="#71717a" font-family="JetBrains Mono, monospace" font-size="9" text-anchor="middle">{{ $rightLabel }}</text>

    <g id="{{ $needleId }}" style="transform-origin: 100px 100px; transform: rotate({{ $rotation }}deg); transition: transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);">
        <line x1="100" y1="100" x2="100" y2="34" stroke="#f4f4f5" stroke-width="2.5" stroke-linecap="square"/>
    </g>
    <circle cx="100" cy="100" r="6" fill="#18181b" stroke="#f4f4f5" stroke-width="2"/>
</svg>

