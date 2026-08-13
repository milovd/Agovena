<div class="admin-page">
    <x-ag.page-header :heading="__('admin.email_log.title')" :lede="__('admin.email_log.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if ($logs->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.email_log.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.email_log.empty_text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('admin.email_log.time') }}</th>
                        <th>{{ __('admin.email_log.to') }}</th>
                        <th>{{ __('admin.email_log.subject') }}</th>
                        <th>{{ __('common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr wire:key="email-log-{{ $log->id }}">
                            <td>{{ $log->created_at?->translatedFormat('d M Y H:i') }}</td>
                            <td>{{ $log->to }}</td>
                            <td>{{ $log->subject ?: '—' }}</td>
                            <td>
                                <span class="ag-badge">{{ __('admin.email_log.statuses.'.$log->status) }}</span>
                                @if ($log->error)
                                    <p class="ag-field__help">{{ $log->error }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
</div>
