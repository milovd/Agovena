<div class="admin-page">
    <x-ag.page-header :heading="__('admin.updates.title')" :lede="__('admin.updates.lede')" />

    <section class="ag-card" aria-labelledby="operations-status-heading">
        <header class="ag-card__header">
            <x-ag.icon-tile name="server" tone="blue" :size="16" />
            <h2 id="operations-status-heading" class="ag-card__title">{{ __('admin.updates.operations_heading') }}</h2>
        </header>
        <div class="ag-card__content">
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
        </div>
    </section>

    <section class="ag-card" aria-labelledby="operations-providers-heading">
        <header class="ag-card__header">
            <x-ag.icon-tile name="puzzle" tone="violet" :size="16" />
            <h2 id="operations-providers-heading" class="ag-card__title">{{ __('admin.updates.providers_heading') }}</h2>
        </header>
        <div class="ag-card__content">
            @if ($providerHealth === [])
                <p class="ag-muted">{{ __('admin.updates.providers_empty') }}</p>
            @else
                <dl class="ag-dl">
                    @foreach ($providerHealth as $provider)
                        <div wire:key="provider-health-{{ $provider['id'] }}">
                            <dt>{{ $provider['name'] }}</dt>
                            <dd>
                                @if ($provider['configured'])
                                    <x-ag.badge variant="success">{{ __('admin.provider_status.configured') }}</x-ag.badge>
                                    @if ($provider['ok'])
                                        {{ __('admin.updates.providers_ok') }}
                                    @else
                                        {{ __('admin.updates.providers_fail') }}
                                    @endif
                                @else
                                    <x-ag.badge variant="warning">{{ __('admin.provider_status.not_configured') }}</x-ag.badge>
                                    @if ($provider['missing'] !== [])
                                        {{ __('admin.provider_status.missing', ['fields' => implode(', ', $provider['missing'])]) }}
                                    @endif
                                    <span class="ag-muted">{{ __('admin.provider_status.not_checked') }}</span>
                                    <a href="{{ route('admin.extensions.index') }}">{{ __('admin.provider_status.open_extension_settings') }}</a>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </section>

    <section class="ag-card" aria-labelledby="operations-packages-heading">
        <header class="ag-card__header">
            <x-ag.icon-tile name="package" tone="indigo" :size="16" />
            <h2 id="operations-packages-heading" class="ag-card__title">{{ __('admin.updates.packages_heading') }}</h2>
        </header>
        <div class="ag-card__content">
            @if ($packageUpdates === [])
                <p class="ag-muted">{{ __('admin.updates.packages_current') }}</p>
            @else
                <ul>
                    @foreach ($packageUpdates as $package)
                        <li>{{ $package['name'] }} ({{ $package['version'] }})</li>
                    @endforeach
                </ul>
            @endif
        </div>
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

        <section class="ag-card" aria-labelledby="operations-command-heading">
            <header class="ag-card__header">
                <x-ag.icon-tile name="scroll-text" tone="cyan" :size="16" />
                <h2 id="operations-command-heading" class="ag-card__title">{{ __('admin.updates.command_heading') }}</h2>
            </header>
            <div class="ag-card__content">
                <p class="ag-field__help">{{ __('admin.updates.command_help') }}</p>
                <pre class="ag-code" tabindex="0"><code>{{ $upgradeCommand }}</code></pre>
                <p class="ag-field__help">{{ __('admin.updates.migrate_alternative', ['command' => $migrateCommand]) }}</p>
                <p class="ag-field__help">{{ __('admin.updates.doctor_hint') }}</p>
            </div>
        </section>

        <section class="ag-card" aria-labelledby="operations-pending-heading">
            <header class="ag-card__header">
                <x-ag.icon-tile name="circle-arrow-up" tone="amber" :size="16" />
                <h2 id="operations-pending-heading" class="ag-card__title">{{ trans_choice('admin.updates.pending_heading', $pendingCount, ['count' => $pendingCount]) }}</h2>
            </header>
            <div class="ag-card__content">
                <div class="ag-table-wrap" role="region" tabindex="0" aria-labelledby="operations-pending-heading">
                    <table class="ag-table">
                        <caption class="visually-hidden">{{ trans_choice('admin.updates.pending_heading', $pendingCount, ['count' => $pendingCount]) }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('admin.updates.migration') }}</th>
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
            </div>
        </section>
    @endif
</div>
