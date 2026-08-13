<section class="admin-panel">
    <h2 class="admin-panel__title">{{ __('subscriptions::admin.title') }}</h2>
    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th>{{ __('subscriptions::admin.number') }}</th>
                    <th>{{ __('subscriptions::admin.status') }}</th>
                    <th>{{ __('subscriptions::admin.next_billing') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $subscription)
                    <tr wire:key="cust-sub-{{ $subscription->id }}">
                        <td>
                            <a href="{{ route('admin.subscriptions.show', $subscription) }}">{{ $subscription->number }}</a>
                            @if ($subscription->product)
                                <span class="ag-muted">{{ $subscription->product->name }}</span>
                            @endif
                        </td>
                        <td>{{ __('subscriptions::status.'.$subscription->status->value) }}</td>
                        <td>{{ $subscription->next_billing_at?->toDateString() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">{{ __('subscriptions::admin.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
