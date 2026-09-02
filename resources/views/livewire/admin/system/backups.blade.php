<div class="admin-page" id="database-backups-overview">
    <x-ag.page-header
        :heading="__('admin.backups.title')"
        :lede="__('admin.backups.lede')"
    >
        <x-slot:actions>
            @can('backups.manage')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="createBackup" wire:loading.attr="disabled" wire:target="createBackup">
                    {{ __('admin.backups.create') }}
                </button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <div class="ag-alert ag-alert--success" role="status" aria-live="polite">
            <div class="ag-alert__body">{{ session('status') }}</div>
        </div>
    @endif

    @if (session('error'))
        <div class="ag-alert ag-alert--danger" role="alert">
            <div class="ag-alert__body">{{ session('error') }}</div>
        </div>
    @endif

    <section class="ag-metrics" aria-label="{{ __('admin.backups.stats_label') }}">
        <article class="ag-metric">
            <p class="ag-metric__label">{{ __('admin.backups.available') }}</p>
            <p class="ag-metric__value">{{ $availableCount }}</p>
            <p class="ag-metric__hint">{{ __('admin.backups.available_hint') }}</p>
        </article>
        <article class="ag-metric">
            <p class="ag-metric__label">{{ __('admin.backups.database') }}</p>
            <p class="ag-metric__value">{{ strtoupper($databaseDriver) }}</p>
            <p class="ag-metric__hint">{{ __('admin.backups.database_hint') }}</p>
        </article>
        <article class="ag-metric">
            <p class="ag-metric__label">{{ __('admin.backups.retention') }}</p>
            <p class="ag-metric__value">{{ $retentionCount }}</p>
            <p class="ag-metric__hint">{{ __('admin.backups.retention_hint', ['days' => $retentionDays]) }}</p>
        </article>
    </section>

    <section class="ag-card" aria-labelledby="backup-schedule-heading">
        <header class="ag-card__header">
            <h2 id="backup-schedule-heading" class="ag-card__title">{{ __('admin.backups.schedule_title') }}</h2>
            <p class="ag-card__description">{{ __('admin.backups.schedule_description') }}</p>
        </header>
        <div class="ag-card__content">
            <form class="ag-form ag-backup-schedule" wire:submit="saveSchedule">
                <div class="ag-backup-schedule__grid">
                    <div class="ag-field ag-backup-schedule__interval">
                        <label class="ag-field__label" for="backup-interval">{{ __('admin.backups.interval_label') }}</label>
                        <select id="backup-interval" class="ag-input" wire:model="backupInterval">
                            @foreach ($intervalOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('backupInterval')
                            <p class="ag-field__error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="ag-field ag-backup-schedule__status">
                        <div class="ag-backup-schedule__status-head">
                            <span class="ag-field__label">{{ __('admin.backups.cron_status') }}</span>
                            @if ($backupInterval === 'disabled')
                                <span class="ag-badge ag-badge--muted">{{ __('admin.backups.cron_disabled') }}</span>
                            @elseif ($schedulerHealthy)
                                <span class="ag-badge ag-badge--success">{{ __('admin.backups.cron_active') }}</span>
                            @else
                                <span class="ag-badge ag-badge--warning">{{ __('admin.backups.cron_attention') }}</span>
                            @endif
                        </div>
                        <p class="ag-field__help">
                            @if ($schedulerLastHeartbeat)
                                {{ __('admin.backups.cron_last_run', ['time' => $schedulerLastHeartbeat->format('Y-m-d H:i')]) }}
                            @else
                                {{ __('admin.backups.cron_not_seen') }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="ag-backup-schedule__footer">
                    @can('backups.manage')
                        <button type="submit" class="ag-btn ag-btn--secondary" wire:loading.attr="disabled" wire:target="saveSchedule">
                            {{ __('admin.backups.save_schedule') }}
                        </button>
                    @endcan
                    <p class="ag-backup-schedule__hint">
                        <x-ag.icon name="calendar" :size="15" />
                        <span>{{ __('admin.backups.cron_hint') }}</span>
                    </p>
                </div>
            </form>
        </div>
    </section>

    <section class="ag-card" aria-labelledby="backup-storage-heading">
        <header class="ag-card__header">
            <h2 id="backup-storage-heading" class="ag-card__title">{{ __('admin.backups.storage_title') }}</h2>
            <p class="ag-card__description">{{ __('admin.backups.storage_description') }}</p>
        </header>
        <div class="ag-card__content">
            <dl class="ag-backup-storage">
                <div class="ag-backup-storage__item">
                    <span class="ag-backup-storage__icon" aria-hidden="true"><x-ag.icon name="server" :size="17" /></span>
                    <div class="ag-backup-storage__copy">
                        <dt>{{ __('admin.backups.storage_label') }}</dt>
                        <dd>{{ $diskName }}</dd>
                    </div>
                </div>
                <div class="ag-backup-storage__item">
                    <span class="ag-backup-storage__icon" aria-hidden="true"><x-ag.icon name="folders" :size="17" /></span>
                    <div class="ag-backup-storage__copy">
                        <dt>{{ __('admin.backups.directory_label') }}</dt>
                        <dd>{{ $directory !== '' ? $directory : '/' }}</dd>
                    </div>
                </div>
                <div class="ag-backup-storage__item ag-backup-storage__item--protected">
                    <span class="ag-backup-storage__icon" aria-hidden="true"><x-ag.icon name="shield" :size="17" /></span>
                    <div class="ag-backup-storage__copy">
                        <dt>{{ __('admin.backups.encryption_label') }}</dt>
                        <dd><span class="ag-badge ag-badge--success">{{ __('admin.backups.encrypted') }}</span></dd>
                    </div>
                </div>
            </dl>
        </div>
    </section>

    <section class="ag-card ag-card--flush" aria-labelledby="backup-files-heading">
        <header class="ag-card__header">
            <h2 id="backup-files-heading" class="ag-card__title">{{ __('admin.backups.files_title') }}</h2>
            <p class="ag-card__description">{{ __('admin.backups.files_description') }}</p>
        </header>
        <div class="ag-card__content">
            <div class="ag-table-wrap">
                <table class="ag-table">
                    <caption class="sr-only">{{ __('admin.backups.files_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.backups.file') }}</th>
                            <th scope="col">{{ __('admin.backups.created_at') }}</th>
                            <th scope="col">{{ __('admin.backups.size') }}</th>
                            <th scope="col">{{ __('admin.backups.status') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('admin.backups.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($files as $file)
                            <tr wire:key="backup-{{ md5($file['path']) }}">
                                <td><code>{{ $file['name'] }}</code></td>
                                <td>{{ date('Y-m-d H:i', $file['modifiedAt']) }}</td>
                                <td>{{ number_format($file['size'] / 1024, 1) }} KB</td>
                                <td><span class="ag-badge ag-badge--success">{{ __('admin.backups.encrypted') }}</span></td>
                                <td class="ag-table__actions">
                                    @can('backups.manage')
                                        <div class="ag-row-actions">
                                            <button
                                                type="button"
                                                class="ag-icon-btn"
                                                wire:click="restoreBackup('{{ $file['path'] }}')"
                                                wire:confirm="{{ __('admin.backups.restore_confirm') }}"
                                                title="{{ __('admin.backups.restore_aria', ['name' => $file['name']]) }}"
                                                aria-label="{{ __('admin.backups.restore_aria', ['name' => $file['name']]) }}"
                                            >
                                                <x-ag.icon name="rotate-ccw" :size="16" />
                                            </button>
                                            <button
                                                type="button"
                                                class="ag-icon-btn ag-icon-btn--danger"
                                                wire:click="deleteBackup('{{ $file['path'] }}')"
                                                wire:confirm="{{ __('admin.backups.delete_confirm') }}"
                                                title="{{ __('admin.backups.delete_aria', ['name' => $file['name']]) }}"
                                                aria-label="{{ __('admin.backups.delete_aria', ['name' => $file['name']]) }}"
                                            >
                                                <x-ag.icon name="trash" :size="16" />
                                            </button>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="ag-empty ag-empty--soft">
                                        <strong class="ag-empty__title">{{ __('admin.backups.empty_title') }}</strong>
                                        <p class="ag-empty__text">{{ __('admin.backups.empty_text') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    @include('livewire.admin.partials.confirm-password-modal')
</div>
