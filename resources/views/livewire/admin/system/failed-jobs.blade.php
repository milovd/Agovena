<div class="admin-page">
    <x-ag.page-header :heading="__('admin.failed_jobs.title')" :lede="auth()->user()?->can('jobs.manage') ? __('admin.failed_jobs.lede') : __('admin.failed_jobs.lede_viewer')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if ($jobs->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.failed_jobs.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.failed_jobs.empty_text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap" role="region" tabindex="0" aria-label="{{ __('admin.failed_jobs.title') }}" wire:loading.class="is-loading" wire:target="gotoPage,previousPage,nextPage">
            <table class="ag-table c-system-table">
                <caption class="visually-hidden">{{ __('admin.failed_jobs.title') }}</caption>
                <thead>
                    <tr>
                        <th>{{ __('admin.failed_jobs.time') }}</th>
                        <th>{{ __('admin.failed_jobs.queue') }}</th>
                        <th>{{ __('admin.failed_jobs.exception') }}</th>
                        @can('jobs.manage')
                            <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jobs as $job)
                        <tr wire:key="failed-job-{{ $job->id }}">
                            <td data-label="{{ __('admin.failed_jobs.time') }}">{{ $job->failed_at }}</td>
                            <td data-label="{{ __('admin.failed_jobs.queue') }}"><code>{{ $job->queue }}</code></td>
                            <td data-label="{{ __('admin.failed_jobs.exception') }}">{{ $job->exception_preview }}</td>
                            @can('jobs.manage')
                                <td class="ag-table__actions" data-label="{{ __('common.actions') }}">
                                    <div class="ag-row-actions">
                                        <button class="ag-btn ag-btn--secondary ag-btn--sm" type="button" wire:click="retry('{{ $job->uuid }}')">{{ __('admin.failed_jobs.retry') }}</button>
                                        <button class="ag-btn ag-btn--danger-outline ag-btn--sm" type="button" wire:click="forget({{ $job->id }})" wire:confirm="{{ __('admin.failed_jobs.delete_confirm') }}">{{ __('common.delete') }}</button>
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $jobs->links() }}
    @endif
</div>
