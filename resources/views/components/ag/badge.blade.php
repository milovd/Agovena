@props([
    'variant' => 'muted',
])

<span {{ $attributes->class(['ag-badge', 'ag-badge--'.$variant]) }}>
    {{ $slot }}
</span>
