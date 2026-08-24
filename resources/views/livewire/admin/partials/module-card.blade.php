@php $manifest = $row['manifest']; @endphp
<article class="ag-package-card" wire:key="module-{{ $manifest->id }}">
    <div class="ag-package-card__top">
        <div>
            <h3 class="ag-package-card__title">{{ $manifest->name }}</h3>
            <p class="ag-muted">{{ $manifest->id }} · v{{ $manifest->version }}</p>
        </div>
        <span @class(['ag-badge', 'ag-badge--success' => $row['enabled'], 'ag-badge--warning' => $row['installed'] && ! $row['enabled'], 'ag-badge--muted' => ! $row['installed']])>{{ __($row['lifecycle']->labelKey()) }}</span>
    </div>
    <p class="ag-package-card__text">{{ $manifest->description }}</p>
    @if (! $row['compatible'])
        <p class="ag-field__error">{{ $row['compatibility_error'] }}</p>
    @endif
    <div class="ag-package-card__actions">
        @can('modules.manage')
            @if (! $row['on_disk'] && $row['compatible'])
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="installFromMonorepo('{{ $row['monorepo_key'] }}')" wire:key="mod-dl-{{ $manifest->id }}">{{ __('admin.packages.actions.download_install') }}</button>
            @elseif (! $row['installed'] && $row['compatible'] && $row['on_disk'])
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="install('{{ $manifest->id }}')" wire:key="mod-install-{{ $manifest->id }}">{{ __('admin.modules.actions.install') }}</button>
            @elseif ($row['enabled'])
                <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="disable('{{ $manifest->id }}')" wire:key="mod-disable-{{ $manifest->id }}">{{ __('admin.modules.actions.disable') }}</button>
                @if ($row['lifecycle']->value === 'update_available')
                    <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="updatePackage('{{ $manifest->id }}')" wire:key="mod-update-{{ $manifest->id }}">{{ __('admin.packages.actions.update') }}</button>
                @endif
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:key="mod-uninstall-{{ $manifest->id }}" x-data @click="if (confirm(@js(__('admin.packages.uninstall_confirm')))) { $wire.uninstallPackage('{{ $manifest->id }}') }">{{ __('admin.packages.actions.uninstall') }}</button>
            @elseif ($row['installed'] && $row['compatible'])
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="enable('{{ $manifest->id }}')" wire:key="mod-enable-{{ $manifest->id }}">{{ __('admin.modules.actions.enable') }}</button>
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:key="mod-uninstall-off-{{ $manifest->id }}" x-data @click="if (confirm(@js(__('admin.packages.uninstall_confirm')))) { $wire.uninstallPackage('{{ $manifest->id }}') }">{{ __('admin.packages.actions.uninstall') }}</button>
            @endif
            @if ($row['can_purge'])
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="purgePackage('{{ $manifest->id }}')" wire:confirm="{{ __('admin.packages.purge_confirm') }}" wire:key="mod-purge-{{ $manifest->id }}">{{ __('admin.packages.actions.purge') }}</button>
            @endif
        @endcan
    </div>
</article>
