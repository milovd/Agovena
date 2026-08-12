@props([
    'label' => null,
    'disabled' => false,
])

@php
    $id = $attributes->get('id') ?? 'switch-'.str_replace('.', '-', uniqid('', true));
@endphp

<label class="ag-switch {{ $disabled ? 'is-disabled' : '' }}" for="{{ $id }}">
    <input
        type="checkbox"
        id="{{ $id }}"
        @disabled($disabled)
        {{ $attributes->class(['ag-switch__input'])->except(['id', 'disabled']) }}
    >
    <span class="ag-switch__track" aria-hidden="true">
        <span class="ag-switch__thumb"></span>
    </span>
    @if ($label || $slot->isNotEmpty())
        <span class="ag-switch__label">{{ $label ?? $slot }}</span>
    @endif
</label>
