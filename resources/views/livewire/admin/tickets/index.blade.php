<div class="admin-page">
    <x-ag.page-header :heading="__('admin.tickets.title')" :lede="__('admin.tickets.lede')" />
    <div class="admin-panel">
        <label class="ag-field__label" for="ticket-status">{{ __('common.status') }}</label>
        <select id="ticket-status" class="ag-select" wire:model.live="status">
            <option value="">{{ __('admin.tickets.all_statuses') }}</option>
            @foreach (\App\Enums\TicketStatus::cases() as $status)
                <option value="{{ $status->value }}">{{ __('admin.tickets.status.'.$status->value) }}</option>
            @endforeach
        </select>
    </div>
    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead><tr><th>{{ __('admin.tickets.number') }}</th><th>{{ __('admin.tickets.subject') }}</th><th>{{ __('admin.tickets.customer') }}</th><th>{{ __('common.status') }}</th><th>{{ __('admin.tickets.assignee') }}</th></tr></thead>
            <tbody>
                @forelse ($tickets as $ticket)
                    <tr>
                        <td><a href="{{ route('admin.tickets.show', $ticket) }}">{{ $ticket->number }}</a></td>
                        <td>{{ $ticket->subject }}</td>
                        <td>{{ $ticket->customer->name }}</td>
                        <td><span class="ag-badge">{{ __('admin.tickets.status.'.$ticket->status->value) }}</span></td>
                        <td>{{ $ticket->assignee?->name ?? __('admin.tickets.unassigned') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('admin.tickets.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $tickets->links() }}
</div>
