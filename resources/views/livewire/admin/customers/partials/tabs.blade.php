<nav class="ag-tabs" aria-label="{{ __('admin.customers.tabs_label') }}">
    @can('customers.view')
        <a
            @class(['ag-tabs__tab', 'ag-tabs__tab--active' => $activeTab === 'customers'])
            href="{{ route('admin.customers.index') }}"
            @if ($activeTab === 'customers') aria-current="page" @else wire:navigate @endif
        >{{ __('admin.nav.customers') }}</a>
    @endcan
    @can('customers.manage')
        <a
            @class(['ag-tabs__tab', 'ag-tabs__tab--active' => $activeTab === 'properties'])
            href="{{ route('admin.customers.properties') }}"
            @if ($activeTab === 'properties') aria-current="page" @else wire:navigate @endif
        >{{ __('admin.nav.customer_properties') }}</a>
    @endcan
</nav>
