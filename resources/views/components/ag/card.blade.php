@props([
    'as' => 'div',
])

<{{ $as }} {{ $attributes->class('ag-card') }}>
    {{ $slot }}
</{{ $as }}>
