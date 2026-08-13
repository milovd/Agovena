@if ($tickets->isNotEmpty())
    <section class="admin-panel">
        <h2 class="admin-panel__title">{{ __('events::admin.customer_heading') }}</h2>
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('events::admin.ticket_number') }}</th>
                        <th>{{ __('events::admin.name') }}</th>
                        <th>{{ __('common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                        <tr>
                            <td>{{ $ticket->number }}</td>
                            <td>{{ $ticket->event?->name }}</td>
                            <td>{{ __('events::admin.ticket_status.'.$ticket->status->value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
