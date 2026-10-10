<div class="admin-page ag-list-page c-ticket-list">
    <x-ag.page-header :heading="__('admin.tickets.title')" :lede="__('admin.tickets.lede')" />

    <div class="ag-toolbar ag-toolbar--filters c-ticket-list__filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="ag-field__label" for="ticket-status">{{ __('common.status') }}</label>
                <select id="ticket-status" class="ag-select" wire:model.live="status">
                    <option value="">{{ __('admin.tickets.all_statuses') }}</option>
                    @foreach (\App\Enums\TicketStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ __('admin.tickets.status.'.$status->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    @if ($tickets->isEmpty())
        <div class="ag-empty" role="status">{{ __('admin.tickets.empty') }}</div>
    @else
        <div class="ag-table-wrap c-ticket-list__wrap" role="region" aria-label="{{ __('admin.tickets.title') }}" tabindex="0" wire:loading.class="is-loading" wire:target="status">
            <table class="ag-table c-ticket-list__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.tickets.subject') }}</th>
                        <th scope="col">{{ __('admin.tickets.customer') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col">{{ __('admin.tickets.assignee') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                        <tr wire:key="ticket-{{ $ticket->id }}">
                            <td class="c-ticket-list__identity">
                                <div class="c-ticket-list__identity-inner">
                                    <x-ag.icon-tile name="ticket" tone="blue" />
                                    <div class="c-ticket-list__primary">
                                        <strong class="ag-table__name">{{ $ticket->subject }}</strong>
                                        <a class="c-ticket-list__number" href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->number }}</a>
                                    </div>
                                </div>
                            </td>
                            <td data-label="{{ __('admin.tickets.customer') }}">{{ $ticket->customer->name }}</td>
                            <td data-label="{{ __('common.status') }}">
                                <span @class([
                                    'ag-badge',
                                    'ag-badge--info' => $ticket->status->value === 'open',
                                    'ag-badge--warning' => $ticket->status->value === 'pending',
                                    'ag-badge--success' => $ticket->status->value === 'answered',
                                    'ag-badge--muted' => $ticket->status->value === 'closed',
                                ])>{{ __('admin.tickets.status.'.$ticket->status->value) }}</span>
                            </td>
                            <td data-label="{{ __('admin.tickets.assignee') }}">{{ $ticket->assignee?->name ?? __('admin.tickets.unassigned') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    {{ $tickets->links() }}
</div>
