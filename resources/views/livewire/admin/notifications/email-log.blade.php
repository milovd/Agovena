<div class="admin-page admin-page--email-log">
    <x-ag.page-header :heading="__('admin.email_log.title')" :lede="__('admin.email_log.lede')" />

    @if ($logs->isEmpty())
        <div class="ag-empty" role="status">
            @if ($logs->total() === 0)
                <p class="ag-empty__title">{{ __('admin.email_log.empty') }}</p>
                <p class="ag-empty__text">{{ __('admin.email_log.empty_text') }}</p>
            @else
                <p class="ag-empty__title">{{ __('admin.email_log.empty_page') }}</p>
                <p class="ag-empty__text">{{ __('admin.email_log.empty_page_text') }}</p>
                <button type="button" class="ag-btn ag-btn--ghost" wire:click="resetPage">{{ __('admin.email_log.first_page') }}</button>
            @endif
        </div>
    @else
        <div class="ag-table-wrap ag-table-wrap--mobile-cards" role="region" tabindex="0" aria-label="{{ __('admin.email_log.title') }}" wire:loading.class="is-loading" wire:target="gotoPage,previousPage,nextPage">
            <table class="ag-table ag-table--mobile-cards ag-table--email-log">
                <caption class="visually-hidden">{{ __('admin.email_log.title') }}</caption>
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
                            <td class="ag-email-log__time" data-label="{{ __('admin.email_log.time') }}">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</td>
                            <td class="ag-email-log__recipient" data-label="{{ __('admin.email_log.to') }}">{{ $log->to }}</td>
                            <td class="ag-email-log__subject" data-label="{{ __('admin.email_log.subject') }}">{{ $log->subject ?: '-' }}</td>
                            <td class="ag-email-log__delivery" data-label="{{ __('common.status') }}">
                                <div class="ag-email-log__delivery-inner">
                                    <span class="ag-badge {{ $log->status === 'sent' ? 'ag-badge--success' : ($log->status === 'failed' ? 'ag-badge--danger' : 'ag-badge--muted') }}">{{ __('admin.email_log.statuses.'.$log->status) }}</span>
                                    @if ($log->error)
                                        <p class="ag-email-log__error">{{ __('admin.email_log.error_hidden') }}</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
</div>
