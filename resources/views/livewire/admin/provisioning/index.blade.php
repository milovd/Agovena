<div class="admin-page">
    <x-ag.page-header :heading="__('provisioning::admin.title')" :lede="__('provisioning::admin.lede')" />

    <div class="ag-toolbar" style="margin-bottom: 1rem;">
        <select class="ag-select" wire:model.live="status" aria-label="{{ __('provisioning::admin.filter_status') }}">
            <option value="">{{ __('provisioning::admin.all_statuses') }}</option>
            <option value="pending">{{ __('provisioning::status.pending') }}</option>
            <option value="provisioning">{{ __('provisioning::status.provisioning') }}</option>
            <option value="active">{{ __('provisioning::status.active') }}</option>
            <option value="suspended">{{ __('provisioning::status.suspended') }}</option>
            <option value="terminated">{{ __('provisioning::status.terminated') }}</option>
            <option value="failed">{{ __('provisioning::status.failed') }}</option>
        </select>
    </div>

    @if ($instances->isEmpty())
        <p class="ag-muted">{{ __('provisioning::admin.empty') }}</p>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('provisioning::admin.number') }}</th>
                        <th>{{ __('common.product') }}</th>
                        <th>{{ __('provisioning::admin.customer') }}</th>
                        <th>{{ __('provisioning::admin.status') }}</th>
                        <th>{{ __('provisioning::admin.external_ref') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instances as $instance)
                        <tr wire:key="svc-{{ $instance->id }}">
                            <td>{{ $instance->number }}</td>
                            <td>{{ $instance->product?->name }}</td>
                            <td>{{ $instance->customer_email }}</td>
                            <td>{{ __('provisioning::status.'.$instance->status->value) }}</td>
                            <td>{{ $instance->external_ref ?? '—' }}</td>
                            <td>
                                <a class="ag-btn ag-btn--ghost" href="{{ route('admin.provisioning.show', $instance) }}">
                                    {{ __('common.view') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
