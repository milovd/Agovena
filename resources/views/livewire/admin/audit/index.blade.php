<div class="admin-page">
    <x-ag.page-header :heading="__('admin.audit.title')" :lede="__('admin.audit.lede')" />
    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead><tr><th>{{ __('admin.audit.time') }}</th><th>{{ __('admin.audit.actor') }}</th><th>{{ __('admin.audit.action') }}</th><th>{{ __('admin.audit.subject') }}</th><th>{{ __('admin.audit.ip') }}</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ __('admin.audit.actor_types.'.$log->actor_type) }}{{ $log->actor_id ? ' #'.$log->actor_id : '' }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td>{{ $log->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('admin.audit.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
