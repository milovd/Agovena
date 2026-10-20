@props([
    'type' => 'date',
])

@php
    /*
     * Native date / datetime-local input enhanced with a shadcn-style calendar
     * popover (resources/js/admin/calendar.js). The input stays the source of
     * truth: typing keeps working and wire:model picks up the picked value.
     */
    $type = $type === 'datetime-local' ? 'datetime-local' : 'date';
@endphp

<span class="ag-date-input">
    <input type="{{ $type }}" {{ $attributes }}>
    <button
        type="button"
        class="ag-date-input__trigger"
        aria-label="{{ __('common.open_calendar') }}"
        aria-haspopup="dialog"
        aria-expanded="false"
        data-ag-calendar-trigger
        data-ag-calendar-label="{{ __('common.calendar') }}"
        data-ag-calendar-previous="{{ __('common.previous_month') }}"
        data-ag-calendar-next="{{ __('common.next_month') }}"
    >
        <x-ag.icon name="calendar" :size="16" />
    </button>
</span>
