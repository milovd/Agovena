@props([
    'href',
    'label',
])

<a
    href="{{ $href }}"
    {{ $attributes->class(['ag-back']) }}
>
    <x-ag.icon name="arrow-left" :size="16" />
    <span>{{ $label }}</span>
</a>
