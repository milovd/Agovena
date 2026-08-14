@props([
    'variant' => 'mark',
    'alt' => 'Agovena',
])

@php
    $src = '/vendor/agovena/logo.png';
    $class = $variant === 'hero' ? 'ag-logo ag-logo--hero' : 'ag-logo';
@endphp

<img
    {{ $attributes->class($class) }}
    src="{{ $src }}"
    alt="{{ $alt }}"
    width="{{ $variant === 'hero' ? 96 : 40 }}"
    height="{{ $variant === 'hero' ? 96 : 40 }}"
    decoding="async"
>
