<nav class="ag-tabs" aria-label="{{ __('subscriptions::admin.tabs_label') }}">
    @can('subscriptions.view')
        <a
            @class(['ag-tabs__tab', 'ag-tabs__tab--active' => $activeTab === 'subscriptions'])
            href="{{ route('admin.subscriptions.index') }}"
            @if ($activeTab === 'subscriptions') aria-current="page" @else wire:navigate @endif
        >{{ __('admin.nav.subscriptions') }}</a>
    @endcan
    @can('plan-changes.view')
        <a
            @class(['ag-tabs__tab', 'ag-tabs__tab--active' => $activeTab === 'plan-changes'])
            href="{{ route('admin.plan-changes.index') }}"
            @if ($activeTab === 'plan-changes') aria-current="page" @else wire:navigate @endif
        >{{ __('admin.nav.plan_changes') }}</a>
    @endcan
</nav>
