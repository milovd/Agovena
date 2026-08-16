<div class="admin-page">
    <x-ag.page-header :heading="__('shipping::returns.admin_title')" :lede="__('shipping::returns.admin_lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-field" style="max-width: 18rem; margin-bottom: 1rem;">
        <label class="ag-field__label" for="returns-status">{{ __('shipping::returns.filter_status') }}</label>
        <select id="returns-status" class="ag-select" wire:model.live="status">
            <option value="">{{ __('shipping::returns.all_statuses') }}</option>
            @foreach ($statuses as $case)
                <option value="{{ $case->value }}">{{ __('shipping::returns.statuses.'.$case->value) }}</option>
            @endforeach
        </select>
    </div>

    @if ($returns->isEmpty())
        <p class="ag-muted">{{ __('shipping::returns.empty') }}</p>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('shipping::returns.order') }}</th>
                        <th>{{ __('shipping::returns.customer') }}</th>
                        <th>{{ __('shipping::returns.status') }}</th>
                        <th>{{ __('shipping::returns.requested_at') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($returns as $request)
                        <tr wire:key="return-{{ $request->id }}">
                            <td>{{ $request->id }}</td>
                            <td>{{ $request->order?->number ?? __('common.em_dash') }}</td>
                            <td>{{ $request->customer_email }}</td>
                            <td>{{ __('shipping::returns.statuses.'.$request->status->value) }}</td>
                            <td>{{ $request->requested_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('common.em_dash') }}</td>
                            <td>
                                <a class="ag-btn ag-btn--ghost" href="{{ route('admin.shipping.returns.show', $request) }}">
                                    {{ __('shipping::returns.view') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $returns->links() }}</div>
    @endif
</div>
