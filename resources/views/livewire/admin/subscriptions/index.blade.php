<div class="admin-page">
    <x-ag.page-header :heading="__('subscriptions::admin.title')" :lede="__('subscriptions::admin.lede')" />

    <div class="ag-toolbar" style="margin-bottom: 1rem;">
        <select class="ag-select" wire:model.live="status" aria-label="{{ __('subscriptions::admin.filter_status') }}">
            <option value="">{{ __('subscriptions::admin.all_statuses') }}</option>
            <option value="active">{{ __('subscriptions::status.active') }}</option>
            <option value="past_due">{{ __('subscriptions::status.past_due') }}</option>
            <option value="cancelled">{{ __('subscriptions::status.cancelled') }}</option>
            <option value="ended">{{ __('subscriptions::status.ended') }}</option>
            <option value="pending">{{ __('subscriptions::status.pending') }}</option>
        </select>
    </div>

    @if ($subscriptions->isEmpty())
        <p class="ag-muted">{{ __('subscriptions::admin.empty') }}</p>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('subscriptions::admin.number') }}</th>
                        <th>{{ __('common.product') }}</th>
                        <th>{{ __('subscriptions::admin.customer') }}</th>
                        <th>{{ __('subscriptions::admin.status') }}</th>
                        <th>{{ __('subscriptions::admin.next_billing') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        <tr wire:key="sub-{{ $subscription->id }}">
                            <td><a class="ag-table__name" href="{{ route('admin.subscriptions.show', $subscription) }}">{{ $subscription->number }}</a></td>
                            <td>{{ $subscription->product?->name }}</td>
                            <td>{{ $subscription->customer_email }}</td>
                            <td>{{ __('subscriptions::status.'.$subscription->status->value) }}</td>
                            <td>{{ $subscription->next_billing_at?->toDateString() ?? '-' }}</td>
                            <td class="ag-table__actions">
                                <a class="ag-icon-btn" href="{{ route('admin.subscriptions.show', $subscription) }}" title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}">
                                    <x-ag.icon name="eye" :size="16" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
