<div class="admin-page">
    <x-ag.page-header :heading="__('admin.failed_jobs.title')" :lede="__('admin.failed_jobs.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if ($jobs->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.failed_jobs.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.failed_jobs.empty_text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('admin.failed_jobs.time') }}</th>
                        <th>{{ __('admin.failed_jobs.queue') }}</th>
                        <th>{{ __('admin.failed_jobs.exception') }}</th>
                        <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jobs as $job)
                        <tr wire:key="failed-job-{{ $job->id }}">
                            <td>{{ $job->failed_at }}</td>
                            <td><code>{{ $job->queue }}</code></td>
                            <td>{{ $job->exception_preview }}</td>
                            <td>
                                <button class="ag-btn ag-btn--secondary ag-btn--sm" type="button" wire:click="retry('{{ $job->uuid }}')">{{ __('admin.failed_jobs.retry') }}</button>
                                <button class="ag-btn ag-btn--danger-outline ag-btn--sm" type="button" wire:click="forget({{ $job->id }})" wire:confirm="{{ __('admin.failed_jobs.delete_confirm') }}">{{ __('common.delete') }}</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $jobs->links() }}
    @endif
</div>
