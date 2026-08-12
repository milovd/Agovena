@props([
    'label' => null,
    'disabled' => false,
])

@php
    $id = $attributes->get('id') ?? 'check-'.str_replace('.', '-', uniqid('', true));
@endphp

<label class="ag-checkbox {{ $disabled ? 'is-disabled' : '' }}" for="{{ $id }}">
    <input
        type="checkbox"
        id="{{ $id }}"
        @disabled($disabled)
        {{ $attributes->class(['ag-checkbox__input'])->except(['id', 'disabled']) }}
    >
    <span class="ag-checkbox__box" aria-hidden="true"></span>
    @if ($label || $slot->isNotEmpty())
        <span class="ag-checkbox__label">{{ $label ?? $slot }}</span>
    @endif
</label>
