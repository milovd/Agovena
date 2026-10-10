<div class="admin-page ag-list-page c-returns">
    <x-ag.page-header :heading="__('shipping::returns.admin_title')" :lede="__('shipping::returns.admin_lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <x-ag.table-toolbar filters class="c-returns__filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field">
                <label class="ag-field__label" for="returns-status">{{ __('shipping::returns.filter_status') }}</label>
                <select id="returns-status" class="ag-select" wire:model.live="status">
                    <option value="">{{ __('shipping::returns.all_statuses') }}</option>
                    @foreach ($statuses as $case)
                        <option value="{{ $case->value }}">{{ __('shipping::returns.statuses.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-ag.table-toolbar>

    @if ($returns->isEmpty())
        <x-ag.empty :title="__('shipping::returns.empty')">
            <x-slot:icon><x-ag.icon name="rotate-ccw" :size="24" /></x-slot:icon>
        </x-ag.empty>
    @else
        <div class="ag-table-wrap c-returns__table-wrap" role="region" aria-label="{{ __('shipping::returns.admin_title') }}" tabindex="0">
            <table class="ag-table c-returns__table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">{{ __('shipping::returns.order') }}</th>
                        <th scope="col">{{ __('shipping::returns.customer') }}</th>
                        <th scope="col">{{ __('shipping::returns.status') }}</th>
                        <th scope="col">{{ __('shipping::returns.requested_at') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($returns as $request)
                        <tr wire:key="return-{{ $request->id }}">
                            <td class="c-returns__identity">
                                <span class="c-returns__identity-inner">
                                    <x-ag.icon-tile name="rotate-ccw" tone="blue" :size="18" />
                                    <span class="c-returns__identity-text">
                                        <span class="ag-table__name">#{{ $request->id }}</span>
                                    </span>
                                </span>
                            </td>
                            <td class="c-returns__detail" data-label="{{ __('shipping::returns.order') }}">{{ $request->order?->number ?? __('common.em_dash') }}</td>
                            <td class="c-returns__detail" data-label="{{ __('shipping::returns.customer') }}">{{ $request->customer_email }}</td>
                            <td class="c-returns__detail" data-label="{{ __('shipping::returns.status') }}">
                                <x-ag.badge :variant="match ($request->status->value) {
                                    'approved', 'completed' => 'success',
                                    'requested' => 'info',
                                    'received' => 'warning',
                                    'rejected' => 'danger',
                                    default => 'muted',
                                }">{{ __('shipping::returns.statuses.'.$request->status->value) }}</x-ag.badge>
                            </td>
                            <td class="c-returns__detail" data-label="{{ __('shipping::returns.requested_at') }}">{{ $request->requested_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('common.em_dash') }}</td>
                            <td class="ag-table__actions c-returns__row-actions">
                                <a class="ag-icon-btn" href="{{ route('admin.shipping.returns.show', $request) }}" title="{{ __('shipping::returns.view') }}" aria-label="{{ __('shipping::returns.view') }}: {{ $request->order?->number ?? '#'.$request->id }}"><x-ag.icon name="eye" :size="16" /></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $returns->links() }}</div>
    @endif
</div>
