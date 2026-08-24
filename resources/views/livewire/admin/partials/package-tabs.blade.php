@props([
    'active',
    'tabs',
])

<nav class="ag-product-tabs ag-package-tabs" role="tablist" aria-label="{{ __('admin.packages.tabs_aria') }}">
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
