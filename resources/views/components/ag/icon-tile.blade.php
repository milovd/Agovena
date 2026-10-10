@props(['name', 'tone' => 'blue', 'size' => 18])
@php
    $tone = in_array($tone, ['emerald', 'blue', 'violet', 'amber', 'indigo', 'cyan'], true) ? $tone : 'blue';
@endphp
<span {{ $attributes->class(['ag-icon-tile'])->merge(['data-tone' => $tone, 'aria-hidden' => 'true']) }}>
    <x-ag.icon :name="$name" :size="$size" />
</span>
