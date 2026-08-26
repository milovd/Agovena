<div class="admin-page">
    <x-ag.page-header :heading="__('domains::admin.title')" :lede="__('domains::admin.lede')" />

    <div class="ag-toolbar" style="margin-bottom: 1rem;">
        <label class="sr-only" for="domain-status">{{ __('domains::admin.filter_status') }}</label>
        <select id="domain-status" class="ag-select" wire:model.live="status">
            <option value="">{{ __('domains::admin.all_statuses') }}</option>
            @foreach (['pending', 'checking', 'registering', 'active', 'renewal_due', 'transfer_pending', 'expired', 'failed', 'cancelled'] as $status)
                <option value="{{ $status }}">{{ __('domains::status.'.$status) }}</option>
            @endforeach
        </select>
    </div>

    @if ($registrations->isEmpty())
        <p class="ag-muted">{{ __('domains::admin.empty') }}</p>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('domains::admin.number') }}</th>
                        <th>{{ __('domains::admin.domain') }}</th>
                        <th>{{ __('domains::admin.customer') }}</th>
                        <th>{{ __('domains::admin.status_label') }}</th>
                        <th>{{ __('domains::admin.provider') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($registrations as $registration)
                        <tr wire:key="domain-{{ $registration->id }}">
                            <td>{{ $registration->number }}</td>
                            <td>{{ $registration->domain_name ?? __('domains::admin.awaiting_domain') }}</td>
                            <td>{{ $registration->customer_email ?? $registration->customer?->email ?? '-' }}</td>
                            <td>{{ __('domains::status.'.$registration->status->value) }}</td>
                            <td>{{ $registration->provider_key ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
