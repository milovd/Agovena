@props([
    'code',
    'width' => 18,
])

@php
    $raw = strtolower(trim((string) $code));

    $aliases = [
        'en' => 'gb',
        'eng' => 'gb',
        'uk' => 'gb',
        'eur' => 'eu',
        'usd' => 'us',
        'gbp' => 'gb',
        'jpy' => 'jp',
        'chf' => 'ch',
        'cad' => 'ca',
        'aud' => 'au',
        'sek' => 'se',
        'nok' => 'no',
        'dkk' => 'dk',
        'pln' => 'pl',
        'nb' => 'no',
        'nn' => 'no',
        'sv' => 'se',
        'da' => 'dk',
        'fi' => 'fi',
        'de' => 'de',
        'fr' => 'fr',
        'es' => 'es',
        'it' => 'it',
        'pt' => 'pt',
        'nl' => 'nl',
        'pl' => 'pl',
    ];

    $flag = $aliases[$raw] ?? $raw;
    $height = (int) max(1, round(((int) $width) * 12 / 18));

    /** Simplified SVG flags - no shared IDs (safe when many flags render). */
    $flags = [
        'gb' => <<<'SVG'
            <path fill="#012169" d="M0 0h640v480H0z"/>
            <path stroke="#fff" stroke-width="60" d="m0 0 640 480M640 0 0 480"/>
            <path stroke="#C8102E" stroke-width="40" d="m0 0 640 480M640 0 0 480"/>
            <path stroke="#fff" stroke-width="100" d="M320 0v480M0 240h640"/>
            <path stroke="#C8102E" stroke-width="60" d="M320 0v480M0 240h640"/>
        SVG,
        'nl' => <<<'SVG'
            <path fill="#21468B" d="M0 0h640v480H0z"/>
            <path fill="#fff" d="M0 0h640v320H0z"/>
            <path fill="#AE1C28" d="M0 0h640v160H0z"/>
        SVG,
        'de' => <<<'SVG'
            <path fill="#FFCE00" d="M0 0h640v480H0z"/>
            <path fill="#D00" d="M0 0h640v320H0z"/>
            <path fill="#000" d="M0 0h640v160H0z"/>
        SVG,
        'fr' => <<<'SVG'
            <path fill="#fff" d="M0 0h640v480H0z"/>
            <path fill="#002654" d="M0 0h213.3v480H0z"/>
            <path fill="#CE1126" d="M426.7 0H640v480H426.7z"/>
        SVG,
        'es' => <<<'SVG'
            <path fill="#AA151B" d="M0 0h640v480H0z"/>
            <path fill="#F1BF00" d="M0 120h640v240H0z"/>
        SVG,
        'it' => <<<'SVG'
            <path fill="#fff" d="M0 0h640v480H0z"/>
            <path fill="#009246" d="M0 0h213.3v480H0z"/>
            <path fill="#CE2B37" d="M426.7 0H640v480H426.7z"/>
        SVG,
        'pt' => <<<'SVG'
            <path fill="#FF0000" d="M0 0h640v480H0z"/>
            <path fill="#006600" d="M0 0h256v480H0z"/>
            <circle cx="256" cy="240" r="80" fill="#FFCC00"/>
            <circle cx="256" cy="240" r="52" fill="#FF0000"/>
            <circle cx="256" cy="240" r="28" fill="#fff"/>
        SVG,
        'pl' => <<<'SVG'
            <path fill="#DC143C" d="M0 0h640v480H0z"/>
            <path fill="#fff" d="M0 0h640v240H0z"/>
        SVG,
        'se' => <<<'SVG'
            <path fill="#005293" d="M0 0h640v480H0z"/>
            <path fill="#FECB00" d="M180 0h100v480H180z"/>
            <path fill="#FECB00" d="M0 190h640v100H0z"/>
        SVG,
        'dk' => <<<'SVG'
            <path fill="#C8102E" d="M0 0h640v480H0z"/>
            <path fill="#fff" d="M180 0h80v480H180z"/>
            <path fill="#fff" d="M0 200h640v80H0z"/>
        SVG,
        'no' => <<<'SVG'
            <path fill="#BA0C2F" d="M0 0h640v480H0z"/>
            <path fill="#fff" d="M180 0h120v480H180z"/>
            <path fill="#fff" d="M0 180h640v120H0z"/>
            <path fill="#00205B" d="M210 0h60v480H210z"/>
            <path fill="#00205B" d="M0 210h640v60H0z"/>
        SVG,
        'fi' => <<<'SVG'
            <path fill="#fff" d="M0 0h640v480H0z"/>
            <path fill="#003580" d="M180 0h100v480H180z"/>
            <path fill="#003580" d="M0 190h640v100H0z"/>
        SVG,
        'eu' => <<<'SVG'
            <path fill="#039" d="M0 0h640v480H0z"/>
            <g fill="#FC0" transform="translate(320 240)">
                <circle cx="0" cy="-72" r="8"/>
                <circle cx="36" cy="-62.4" r="8"/>
                <circle cx="62.4" cy="-36" r="8"/>
                <circle cx="72" cy="0" r="8"/>
                <circle cx="62.4" cy="36" r="8"/>
                <circle cx="36" cy="62.4" r="8"/>
                <circle cx="0" cy="72" r="8"/>
                <circle cx="-36" cy="62.4" r="8"/>
                <circle cx="-62.4" cy="36" r="8"/>
                <circle cx="-72" cy="0" r="8"/>
                <circle cx="-62.4" cy="-36" r="8"/>
                <circle cx="-36" cy="-62.4" r="8"/>
            </g>
        SVG,
        'us' => <<<'SVG'
            <path fill="#B22234" d="M0 0h640v480H0z"/>
            <path stroke="#fff" stroke-width="37" d="M0 55.3h640M0 129.1h640M0 203h640M0 276.9h640M0 350.7h640M0 424.6h640"/>
            <path fill="#3C3B6E" d="M0 0h256v259H0z"/>
        SVG,
        'jp' => <<<'SVG'
            <path fill="#fff" d="M0 0h640v480H0z"/>
            <circle cx="320" cy="240" r="96" fill="#BC002D"/>
        SVG,
        'ch' => <<<'SVG'
            <path fill="#D52B1E" d="M0 0h640v480H0z"/>
            <path fill="#fff" d="M270 90h100v300H270z"/>
            <path fill="#fff" d="M170 190h300v100H170z"/>
        SVG,
        'ca' => <<<'SVG'
            <path fill="#fff" d="M0 0h640v480H0z"/>
            <path fill="#FF0000" d="M0 0h160v480H0zm480 0h160v480H480z"/>
            <path fill="#FF0000" d="M320 96l28 72 78-18-42 68 58 54-80-6-22 78-22-78-80 6 58-54-42-68 78 18z"/>
        SVG,
        'au' => <<<'SVG'
            <path fill="#00008B" d="M0 0h640v480H0z"/>
            <path fill="#fff" d="m0 0 256 192L0 0zm256 0L0 192 256 0z"/>
            <path stroke="#FF0000" stroke-width="24" d="m0 0 256 192M256 0 0 192"/>
            <circle cx="480" cy="300" r="18" fill="#fff"/>
            <circle cx="430" cy="250" r="12" fill="#fff"/>
            <circle cx="530" cy="250" r="12" fill="#fff"/>
            <circle cx="450" cy="340" r="12" fill="#fff"/>
            <circle cx="510" cy="340" r="12" fill="#fff"/>
        SVG,
    ];

    $inner = $flags[$flag] ?? null;
@endphp

@if ($inner === null)
    <svg
        {{ $attributes->class(['ag-flag', 'ag-flag--fallback'])->merge(['aria-hidden' => 'true', 'focusable' => 'false']) }}
        xmlns="http://www.w3.org/2000/svg"
        width="{{ $width }}"
        height="{{ $height }}"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <circle cx="12" cy="12" r="10"/>
        <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/>
        <path d="M2 12h20"/>
    </svg>
@else
    <svg
        {{ $attributes->class(['ag-flag'])->merge(['aria-hidden' => 'true', 'focusable' => 'false']) }}
        xmlns="http://www.w3.org/2000/svg"
        width="{{ $width }}"
        height="{{ $height }}"
        viewBox="0 0 640 480"
        preserveAspectRatio="xMidYMid slice"
    >
        {!! $inner !!}
    </svg>
@endif
