@props(['label', 'value', 'hint' => null, 'href' => null, 'linkLabel' => null, 'icon', 'tone' => 'blue'])
<article {{ $attributes->class(['ag-metric'])->merge(['role' => 'listitem']) }}>
    <div class="ag-metric__heading">
        <x-ag.icon-tile :name="$icon" :tone="$tone" :size="24" />
        <p class="ag-metric__label">{{ $label }}</p>
    </div>
    <p class="ag-metric__value">{{ $value }}</p>
    @if ($hint)
        <p class="ag-metric__hint">{{ $hint }}</p>
    @endif
    @if ($href)
        <a class="ag-metric__link" href="{{ $href }}">{{ $linkLabel ?? $label }} <x-ag.icon name="arrow-right" :size="14" /></a>
    @endif
</article>
