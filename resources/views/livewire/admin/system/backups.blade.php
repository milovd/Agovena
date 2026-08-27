<div class="admin-page" id="database-backups-overview">
    <header class="admin-page__header">
        <div>
            <p class="admin-page__eyebrow">{{ __('admin.nav_groups.system') }}</p>
            <h1 class="admin-page__heading">{{ __('admin.backups.title') }}</h1>
            <div class="admin-page__intro">
                <p class="admin-page__lede">{{ __('admin.backups.lede') }}</p>
                <p class="admin-page__note">{{ __('admin.backups.security_note') }}</p>
            </div>
        </div>

        @can('backups.manage')
            <div class="admin-page__actions">
                <button type="button" class="ag-btn ag-btn--primary" wire:click="createBackup" wire:loading.attr="disabled" wire:target="createBackup">
                    {{ __('admin.backups.create') }}
                </button>
            </div>
        @endcan
    </header>

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

    <section class="ag-stats" aria-label="{{ __('admin.backups.stats_label') }}">
        <div class="ag-stats__item">
            <p class="ag-stats__label">{{ __('admin.backups.available') }}</p>
            <p class="ag-stats__value">{{ $availableCount }}</p>
            <p class="ag-stats__hint">{{ __('admin.backups.available_hint') }}</p>
        </div>
        <div class="ag-stats__item">
            <p class="ag-stats__label">{{ __('admin.backups.database') }}</p>
            <p class="ag-stats__value">{{ strtoupper($databaseDriver) }}</p>
            <p class="ag-stats__hint">{{ __('admin.backups.database_hint') }}</p>
        </div>
        <div class="ag-stats__item">
            <p class="ag-stats__label">{{ __('admin.backups.retention') }}</p>
            <p class="ag-stats__value">{{ $retentionCount }}</p>
            <p class="ag-stats__hint">{{ __('admin.backups.retention_hint', ['days' => $retentionDays]) }}</p>
        </div>
    </section>

    <section class="ag-card" aria-labelledby="backup-storage-heading">
        <header class="ag-card__header">
            <h2 id="backup-storage-heading" class="ag-card__title">{{ __('admin.backups.storage_title') }}</h2>
            <p class="ag-card__description">{{ __('admin.backups.storage_description') }}</p>
        </header>
        <div class="ag-card__content">
            <dl class="ag-dl">
                <div>
                    <dt>{{ __('admin.backups.storage_label') }}</dt>
                    <dd>{{ $diskName }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.backups.directory_label') }}</dt>
                    <dd>{{ $directory !== '' ? $directory : '/' }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.backups.encryption_label') }}</dt>
                    <dd><span class="ag-badge ag-badge--success">{{ __('admin.backups.encrypted') }}</span></dd>
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
                <table class="admin-table">
                    <caption class="sr-only">{{ __('admin.backups.files_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.backups.file') }}</th>
                            <th scope="col">{{ __('admin.backups.created_at') }}</th>
                            <th scope="col">{{ __('admin.backups.size') }}</th>
                            <th scope="col">{{ __('admin.backups.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($files as $file)
                            <tr>
                                <td><code>{{ $file['name'] }}</code></td>
                                <td>{{ date('Y-m-d H:i', $file['modifiedAt']) }}</td>
                                <td>{{ number_format($file['size'] / 1024, 1) }} KB</td>
                                <td><span class="ag-badge ag-badge--success">{{ __('admin.backups.encrypted') }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
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
</div>
