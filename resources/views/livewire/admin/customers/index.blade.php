<div class="admin-page">
    <x-ag.page-header :heading="__('admin.customers.title')" :lede="__('admin.customers.lede')" />
    <div class="admin-panel">
        <label class="ag-field__label" for="customer-search">{{ __('common.search') }}</label>
        <input id="customer-search" class="ag-input" type="search" wire:model.live.debounce.300ms="search">
    </div>
    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead><tr>
                <th>{{ __('common.name') }}</th>
                <th>{{ __('admin.customers.email') }}</th>
                <th>{{ __('common.status') }}</th>
                <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
            </tr></thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>
                            @if ($customer->anonymized_at)
                                <span class="ag-badge">{{ __('admin.customers.anonymized_badge') }}</span>
                            @elseif ($customer->deletion_requested_at)
                                <span class="ag-badge">{{ __('admin.customers.deletion_requested_badge') }}</span>
                            @endif
                        </td>
                        <td><a class="ag-btn ag-btn--ghost" href="{{ route('admin.customers.show', $customer) }}">{{ __('common.view') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4">{{ __('admin.customers.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</div>
