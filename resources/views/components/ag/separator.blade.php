@props([
    'decorative' => true,
])

<hr
    {{ $attributes->class('ag-separator') }}
    @if ($decorative) role="none" @else role="separator" @endif
>
