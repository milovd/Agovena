@props([
    'id',
    'field',
    'selected' => '',
    'placeholder' => '',
    'open' => false,
    'results' => [],
    'disabled' => false,
    'invalid' => false,
])
{{--
    Search select driven by the Livewire component: openPicker(field), pickerTerm, pick(field, id), closePicker().
    Closed it shows the chosen value with a chevron; open it shows a search field and the matches.
--}}
<div {{ $attributes->class(['ag-search-select', 'is-open' => $open, 'is-disabled' => $disabled]) }}
    x-data="agSearchSelect" data-close-method="closePicker" wire:key="{{ $id }}-{{ $open ? 'open' : 'closed' }}"
    @click.outside="close()" @keydown.escape.window="close()">
    @if ($disabled)
        <div id="{{ $id }}" class="ag-search-select__control" aria-disabled="true">
            <span class="ag-search-select__value">{{ $selected !== '' ? $selected : $placeholder }}</span>
            <x-ag.icon name="lock" :size="15" />
        </div>
    @else
        <button id="{{ $id }}" type="button" @class(['ag-search-select__control', 'is-invalid' => $invalid])
            wire:click="{{ $open ? 'closePicker' : "openPicker('".$field."')" }}"
            aria-haspopup="listbox" aria-expanded="{{ $open ? 'true' : 'false' }}">
            <span @class(['ag-search-select__value', 'is-placeholder' => $selected === ''])>{{ $selected !== '' ? $selected : $placeholder }}</span>
            <x-ag.icon name="chevron-down" :size="16" />
        </button>
    @endif

    @if ($open && ! $disabled)
        <div class="ag-search-select__panel">
            <div class="ag-search-select__search">
                <x-ag.icon name="search" :size="15" />
                <input type="search" wire:model.live.debounce.250ms="pickerTerm" placeholder="{{ __('admin.orders.inline.search') }}"
                    autocomplete="off" aria-label="{{ __('admin.orders.inline.search') }}">
            </div>
            <div class="ag-search-select__options" role="listbox">
                @forelse ($results as $result)
                    <button type="button" class="ag-search-select__option" role="option"
                        wire:key="{{ $id }}-option-{{ $result['id'] }}"
                        wire:click="pick('{{ $field }}', {{ $result['id'] }})">
                        <span>{{ $result['label'] }}</span>
                        @if (! empty($result['hint']))
                            <span class="ag-search-select__hint">{{ $result['hint'] }}</span>
                        @endif
                    </button>
                @empty
                    <p class="ag-search-select__empty">{{ __('admin.orders.inline.no_results') }}</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
