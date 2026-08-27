@php
    /** @var \App\Agovena\Extensions\ExtensionManifest $manifest */
    $manifest = $row['manifest'];
@endphp
<article class="ag-package-card" wire:key="extension-{{ $manifest->id }}">
    <div class="ag-package-card__top">
        <div>
            <h3 class="ag-package-card__title">{{ $manifest->name }}</h3>
            <p class="ag-muted">{{ $manifest->id }} · v{{ $manifest->version }}</p>
        </div>
        <span @class([
            'ag-badge',
            'ag-badge--success' => $row['enabled'],
            'ag-badge--warning' => $row['installed'] && ! $row['enabled'],
            'ag-badge--muted' => ! $row['installed'],
        ])>{{ __($row['lifecycle']->labelKey()) }}</span>
    </div>
    <p class="ag-package-card__text">{{ $manifest->description }}</p>
    @if (! $manifest->productionReady && ! app()->environment(['local', 'testing']))
        <p class="ag-field__error">{{ __('admin.extensions.not_production_ready', ['extension' => $manifest->id]) }}</p>
    @endif
    <div class="ag-package-card__meta">
        <span class="ag-muted">{{ __('admin.packages.column_source') }}: {{ __('admin.packages.source.'.$row['source']->value) }}</span>
        <span class="ag-muted">{{ __('admin.extensions.column_author') }}: {{ $manifest->author }}</span>
        @if ($row['compatible'])
            <span class="ag-muted">{{ __('admin.extensions.column_compatibility') }}: {{ $manifest->agovena }}</span>
        @else
            <span class="ag-field__error">{{ $row['compatibility_error'] }}</span>
        @endif
    </div>
    <div class="ag-package-card__actions">
        @can('extensions.manage')
            @if (! $row['on_disk'] && $row['compatible'] && ($manifest->productionReady || app()->environment(['local', 'testing'])))
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="installFromMonorepo('{{ $row['monorepo_key'] }}')" wire:key="ext-dl-{{ $manifest->id }}">
                    {{ __('admin.packages.actions.download_install') }}
                </button>
            @elseif (! $row['installed'] && $row['compatible'] && $row['on_disk'] && ($manifest->productionReady || app()->environment(['local', 'testing'])))
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="install('{{ $manifest->id }}')" wire:key="ext-install-{{ $manifest->id }}">
                    {{ __('admin.extensions.actions.install') }}
                </button>
            @elseif ($row['enabled'])
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="disable('{{ $manifest->id }}')" wire:key="ext-disable-{{ $manifest->id }}">
                    {{ __('admin.extensions.actions.disable') }}
                </button>
                @if ($manifest->settings !== [])
                    <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click.prevent.stop="openSettings('{{ $manifest->id }}')" wire:key="ext-settings-{{ $manifest->id }}">
                        {{ __('admin.extensions.actions.settings') }}
                    </button>
                @endif
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="runHealth('{{ $manifest->id }}')" wire:key="ext-health-{{ $manifest->id }}">
                    {{ __('admin.extensions.actions.health') }}
                </button>
                <button
                    type="button"
                    class="ag-btn ag-btn--ghost ag-btn--sm"
                    wire:key="ext-uninstall-{{ $manifest->id }}"
                    x-data
                    @click="if (confirm(@js(__('admin.packages.uninstall_confirm')))) { $wire.uninstallPackage('{{ $manifest->id }}') }"
                >
                    {{ __('admin.packages.actions.uninstall') }}
                </button>
            @elseif ($row['installed'] && $row['compatible'] && ($manifest->productionReady || app()->environment(['local', 'testing'])))
                <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="enable('{{ $manifest->id }}')" wire:key="ext-enable-{{ $manifest->id }}">
                    {{ __('admin.extensions.actions.enable') }}
                </button>
                <button
                    type="button"
                    class="ag-btn ag-btn--ghost ag-btn--sm"
                    wire:key="ext-uninstall-disabled-{{ $manifest->id }}"
                    x-data
                    @click="if (confirm(@js(__('admin.packages.uninstall_confirm')))) { $wire.uninstallPackage('{{ $manifest->id }}') }"
                >
                    {{ __('admin.packages.actions.uninstall') }}
                </button>
            @endif
            @if ($row['lifecycle']->value === 'update_available')
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="updatePackage('{{ $manifest->id }}')" wire:key="ext-update-{{ $manifest->id }}">
                    {{ __('admin.packages.actions.update') }}
                </button>
            @endif
            @if ($row['can_purge'])
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="purgePackage('{{ $manifest->id }}')" wire:confirm="{{ __('admin.packages.purge_confirm') }}" wire:key="ext-purge-{{ $manifest->id }}">
                    {{ __('admin.packages.actions.purge') }}
                </button>
            @endif
        @endcan
    </div>
</article>
