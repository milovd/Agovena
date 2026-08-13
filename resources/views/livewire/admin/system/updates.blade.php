<div class="admin-page">
    <x-ag.page-header :heading="__('admin.updates.title')" :lede="__('admin.updates.lede')" />

    <section class="admin-panel">
        <h3 class="admin-panel__title">{{ __('admin.updates.operations_heading') }}</h3>
        <dl class="ag-dl">
            <div>
                <dt>{{ __('admin.updates.platform_version') }}</dt>
                <dd>{{ $platformVersion }}</dd>
            </div>
            <div>
                <dt>{{ __('admin.updates.scheduler') }}</dt>
                <dd>
                    @if ($scheduler['stale'])
                        {{ __('admin.updates.scheduler_stale', ['time' => $scheduler['last'] ?? __('admin.updates.scheduler_never')]) }}
                    @elseif ($scheduler['last'])
                        {{ __('admin.updates.scheduler_ok', ['time' => $scheduler['last']]) }}
                    @else
                        {{ __('admin.updates.scheduler_idle') }}
                    @endif
                </dd>
            </div>
            <div>
                <dt>{{ __('admin.updates.queue') }}</dt>
                <dd>{{ $queue }}</dd>
            </div>
            <div>
                <dt>{{ __('admin.updates.mail') }}</dt>
                <dd>{{ $mail }}</dd>
            </div>
            <div>
                <dt>{{ __('admin.failed_jobs.title') }}</dt>
                <dd>{{ __('admin.updates.failed_jobs_count', ['count' => $failedJobs]) }}</dd>
            </div>
        </dl>
        <p class="ag-muted">{{ __('admin.updates.no_core_self_update') }}</p>
    </section>

    <section class="admin-panel">
        <h3 class="admin-panel__title">{{ __('admin.updates.packages_heading') }}</h3>
        @if ($packageUpdates === [])
            <p class="ag-muted">{{ __('admin.updates.packages_current') }}</p>
        @else
            <ul>
                @foreach ($packageUpdates as $package)
                    <li>{{ $package['name'] }} ({{ $package['version'] }})</li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($pendingCount === 0)
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.updates.current_title') }}</p>
            <p class="ag-empty__text">{{ __('admin.updates.current_text') }}</p>
        </div>
    @else
        <div class="ag-alert ag-alert--warning" role="status">
            <div class="ag-alert__body">
                <p class="ag-alert__title">{{ __('admin.updates.pending_title') }}</p>
                <p class="ag-alert__text">{{ __('admin.updates.pending_text') }}</p>
            </div>
        </div>

        <section class="admin-panel">
            <h3 class="admin-panel__title">{{ __('admin.updates.command_heading') }}</h3>
            <p class="ag-field__help">{{ __('admin.updates.command_help') }}</p>
            <pre class="ag-code" tabindex="0"><code>{{ $upgradeCommand }}</code></pre>
            <p class="ag-field__help">{{ __('admin.updates.migrate_alternative', ['command' => $migrateCommand]) }}</p>
            <p class="ag-field__help">{{ __('admin.updates.doctor_hint') }}</p>
        </section>

        <section class="admin-panel">
            <h3 class="admin-panel__title">{{ trans_choice('admin.updates.pending_heading', $pendingCount, ['count' => $pendingCount]) }}</h3>
            <div class="ag-table-wrap">
                <table class="ag-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.updates.migration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending as $migration)
                            <tr wire:key="migration-{{ $migration }}">
                                <td><code>{{ $migration }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
