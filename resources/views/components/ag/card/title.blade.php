@props([
    'as' => 'h2',
])

<{{ $as }} {{ $attributes->class('ag-card__title') }}>
    {{ $slot }}
</{{ $as }}>
