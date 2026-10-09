<nav class="ag-tabs" aria-label="{{ __('admin.invoice_design.tabs_label') }}">
    @can('invoices.view')
        <a
            @class(['ag-tabs__tab', 'ag-tabs__tab--active' => $activeTab === 'invoices'])
            href="{{ route('admin.invoices.index') }}"
            @if ($activeTab === 'invoices') aria-current="page" @else wire:navigate @endif
        >{{ __('admin.invoice_design.tab_invoices') }}</a>
    @endcan
    @can('invoices.design')
        <a
            @class(['ag-tabs__tab', 'ag-tabs__tab--active' => $activeTab === 'design'])
            href="{{ route('admin.invoices.design') }}"
            @if ($activeTab === 'design') aria-current="page" @else wire:navigate @endif
        >{{ __('admin.invoice_design.tab_design') }}</a>
    @endcan
</nav>
