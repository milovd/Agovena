@props([
    'active',
    'tabs',
    'ariaLabel',
])

<nav class="ag-product-tabs ag-section-tabs" role="tablist" aria-label="{{ $ariaLabel }}">
    @foreach ($tabs as $key => $label)
        <button
            type="button"
            class="ag-product-tabs__tab {{ $active === $key ? 'is-active' : '' }}"
            role="tab"
            aria-selected="{{ $active === $key ? 'true' : 'false' }}"
            wire:click="$set('tab', '{{ $key }}')"
        >
            {{ $label }}
        </button>
    @endforeach
</nav>
