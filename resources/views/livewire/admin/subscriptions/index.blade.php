<div class="admin-page ag-list-page c-subscriptions">
    <x-ag.page-header :heading="__('subscriptions::admin.title')" :lede="__('subscriptions::admin.lede')" />
    @include('livewire.admin.subscriptions.partials.tabs', ['activeTab' => 'subscriptions'])

    <x-ag.table-toolbar filters class="c-subscriptions__filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="subscription-status">{{ __('subscriptions::admin.filter_status') }}</label>
                <select id="subscription-status" class="ag-select" wire:model.live="status">
                    <option value="">{{ __('subscriptions::admin.all_statuses') }}</option>
                    <option value="active">{{ __('subscriptions::status.active') }}</option>
                    <option value="past_due">{{ __('subscriptions::status.past_due') }}</option>
                    <option value="cancelled">{{ __('subscriptions::status.cancelled') }}</option>
                    <option value="ended">{{ __('subscriptions::status.ended') }}</option>
                    <option value="pending">{{ __('subscriptions::status.pending') }}</option>
                </select>
            </div>
        </div>
    </x-ag.table-toolbar>

    @if ($subscriptions->isEmpty())
        <x-ag.empty :title="__('subscriptions::admin.empty')">
            <x-slot:icon><x-ag.icon name="repeat" :size="24" /></x-slot:icon>
        </x-ag.empty>
    @else
        <div class="ag-table-wrap c-subscriptions__table-wrap" role="region" aria-label="{{ __('subscriptions::admin.title') }}" tabindex="0">
            <table class="ag-table c-subscriptions__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('subscriptions::admin.number') }}</th>
                        <th scope="col">{{ __('common.product') }}</th>
                        <th scope="col">{{ __('subscriptions::admin.customer') }}</th>
                        <th scope="col">{{ __('subscriptions::admin.status') }}</th>
                        <th scope="col">{{ __('subscriptions::admin.next_billing') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        <tr wire:key="sub-{{ $subscription->id }}">
                            <td class="c-subscriptions__identity">
                                <div class="c-subscriptions__identity-inner">
                                    <x-ag.icon-tile name="repeat" tone="blue" :size="16" />
                                    <a class="ag-table__name" href="{{ route('admin.subscriptions.show', $subscription) }}">{{ $subscription->number }}</a>
                                </div>
                            </td>
                            <td class="c-subscriptions__detail" data-label="{{ __('common.product') }}">{{ $subscription->product?->name }}</td>
                            <td class="c-subscriptions__detail" data-label="{{ __('subscriptions::admin.customer') }}">{{ $subscription->customer_email }}</td>
                            <td class="c-subscriptions__detail" data-label="{{ __('subscriptions::admin.status') }}">
                                <x-ag.badge :variant="match ($subscription->status->value) { 'active' => 'success', 'past_due', 'pending' => 'warning', default => 'muted' }">{{ __('subscriptions::status.'.$subscription->status->value) }}</x-ag.badge>
                            </td>
                            <td class="c-subscriptions__detail" data-label="{{ __('subscriptions::admin.next_billing') }}">{{ $subscription->next_billing_at?->toDateString() ?? '-' }}</td>
                            <td class="ag-table__actions c-subscriptions__row-actions">
                                <x-ag.row-actions>
                                    <a class="ag-icon-btn" href="{{ route('admin.subscriptions.show', $subscription) }}" title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}: {{ $subscription->number }}">
                                        <x-ag.icon name="eye" :size="16" />
                                    </a>
                                </x-ag.row-actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
