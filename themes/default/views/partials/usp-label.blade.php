@php
    $formatUspLabel = static function (string $text, string $emphasis): string {
        if ($emphasis !== '' && str_starts_with(mb_strtolower($text), mb_strtolower($emphasis))) {
            return '<strong>'.e($emphasis).'</strong>'.e(mb_substr($text, mb_strlen($emphasis)));
        }
        if ($emphasis !== '') {
            return '<strong>'.e($emphasis).'</strong> '.e($text);
        }

        return e($text);
    };

    $fullHtml = $formatUspLabel($usp['text'], $usp['emphasis']);
    $short = $usp['short'] !== '' ? $usp['short'] : $usp['text'];
    $shortHtml = $formatUspLabel($short, $usp['emphasis']);
    $hasShort = $short !== $usp['text'];
@endphp
<span class="store-usp__label">
    <span @class(['store-usp__label-full', 'store-usp__label-full--only' => ! $hasShort])>{!! $fullHtml !!}</span>
    @if ($hasShort)
        <span class="store-usp__label-short" aria-hidden="true">{!! $shortHtml !!}</span>
    @endif
</span>
