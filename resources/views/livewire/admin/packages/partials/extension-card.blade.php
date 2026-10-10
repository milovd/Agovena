@php
    /** @var \App\Agovena\Extensions\ExtensionManifest $manifest */
    $manifest = $row['manifest'];
@endphp
<x-ag.package-card wire:key="extension-{{ $manifest->id }}">
    <div class="ag-package-card__media">
        <x-ag.package-mark :manifest="$manifest" kind="extension" :on-disk="$row['on_disk']" />
    </div>
    <div class="ag-package-card__body">
        <div class="ag-package-card__top">
            <div class="ag-package-card__identity">
                <h3 class="ag-package-card__title">{{ $manifest->name }}</h3>
                <p class="ag-package-card__version">{{ $manifest->id }} · v{{ $manifest->version }}</p>
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
        <ul class="ag-package-card__meta" role="list">
            <li><x-ag.icon name="package" :size="14" />{{ __('admin.packages.column_source') }}: {{ __('admin.packages.source.'.$row['source']->value) }}</li>
            <li><x-ag.icon name="user" :size="14" />{{ __('admin.extensions.column_author') }}: {{ $manifest->author }}</li>
            @if ($row['compatible'])
                <li><x-ag.icon name="check" :size="14" />{{ __('admin.extensions.column_compatibility') }}: {{ $manifest->agovena }}</li>
            @else
                <li class="ag-field__error"><x-ag.icon name="circle-alert" :size="14" />{{ $row['compatibility_error'] }}</li>
            @endif
        </ul>
        <div class="ag-package-card__controls">
        @can('extensions.manage')
            @if (! $row['on_disk'] && $row['compatible'] && ($manifest->productionReady || app()->environment(['local', 'testing'])))
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="installFromMonorepo('{{ $row['monorepo_key'] }}')" wire:key="ext-dl-{{ $manifest->id }}">
                    <x-ag.icon name="download" :size="16" /> {{ __('admin.packages.actions.download_install') }}
                </button>
            @elseif (! $row['installed'] && $row['compatible'] && $row['on_disk'] && ($manifest->productionReady || app()->environment(['local', 'testing'])))
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="install('{{ $manifest->id }}')" wire:key="ext-install-{{ $manifest->id }}">
                    <x-ag.icon name="download" :size="16" /> {{ __('admin.extensions.actions.install') }}
                </button>
            @elseif ($row['enabled'])
                <button type="button" class="ag-package-card__icon-action" wire:click="disable('{{ $manifest->id }}')" wire:key="ext-disable-{{ $manifest->id }}" aria-label="{{ __('admin.extensions.actions.disable') }}" title="{{ __('admin.extensions.actions.disable') }}">
                    <x-ag.icon name="circle" :size="17" />
                </button>
                @if ($manifest->settings !== [])
                    <button type="button" class="ag-package-card__icon-action" wire:click.prevent.stop="openSettings('{{ $manifest->id }}')" wire:key="ext-settings-{{ $manifest->id }}" aria-label="{{ __('admin.extensions.actions.settings') }}" title="{{ __('admin.extensions.actions.settings') }}">
                        <x-ag.icon name="settings" :size="17" />
                    </button>
                @endif
                <button type="button" class="ag-package-card__icon-action" wire:click="runHealth('{{ $manifest->id }}')" wire:key="ext-health-{{ $manifest->id }}" aria-label="{{ __('admin.extensions.actions.health') }}" title="{{ __('admin.extensions.actions.health') }}">
                    <x-ag.icon name="repeat" :size="17" />
                </button>
                <button
                    type="button"
                    class="ag-package-card__icon-action ag-package-card__icon-action--danger"
                    wire:key="ext-uninstall-{{ $manifest->id }}"
                    wire:confirm="{{ __('admin.packages.uninstall_confirm') }}"
                    wire:click="uninstallPackage('{{ $manifest->id }}')"
                    aria-label="{{ __('admin.packages.actions.uninstall') }}"
                    title="{{ __('admin.packages.actions.uninstall') }}"
                >
                    <x-ag.icon name="trash" :size="17" />
                </button>
            @elseif ($row['installed'] && $row['compatible'] && ($manifest->productionReady || app()->environment(['local', 'testing'])))
                <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="enable('{{ $manifest->id }}')" wire:key="ext-enable-{{ $manifest->id }}">
                    {{ __('admin.extensions.actions.enable') }}
                </button>
                <button
                    type="button"
                    class="ag-package-card__icon-action ag-package-card__icon-action--danger"
                    wire:key="ext-uninstall-disabled-{{ $manifest->id }}"
                    wire:confirm="{{ __('admin.packages.uninstall_confirm') }}"
                    wire:click="uninstallPackage('{{ $manifest->id }}')"
                    aria-label="{{ __('admin.packages.actions.uninstall') }}"
                    title="{{ __('admin.packages.actions.uninstall') }}"
                >
                    <x-ag.icon name="trash" :size="17" />
                </button>
            @endif
            @if ($row['lifecycle']->value === 'update_available')
                <button type="button" class="ag-package-card__icon-action" wire:click="updatePackage('{{ $manifest->id }}')" wire:key="ext-update-{{ $manifest->id }}" aria-label="{{ __('admin.packages.actions.update') }}" title="{{ __('admin.packages.actions.update') }}">
                    <x-ag.icon name="download" :size="17" />
                </button>
            @endif
            @if ($row['can_purge'])
                <button type="button" class="ag-package-card__icon-action ag-package-card__icon-action--danger" wire:click="purgePackage('{{ $manifest->id }}')" wire:confirm="{{ __('admin.packages.purge_confirm') }}" wire:key="ext-purge-{{ $manifest->id }}" aria-label="{{ __('admin.packages.actions.purge') }}" title="{{ __('admin.packages.actions.purge') }}">
                    <x-ag.icon name="trash" :size="17" />
                </button>
            @endif
        @endcan
    </div>
    </div>
</x-ag.package-card>
