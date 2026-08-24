@php
    $manifest = $row['manifest'];
    $otherPresetLabels = $otherPresetLabels ?? [];
    $uninstallConfirm = $otherPresetLabels !== []
        ? __('admin.modules.uninstall_shared_confirm', [
            'module' => $manifest->name,
            'presets' => implode(', ', $otherPresetLabels),
        ])
        : __('admin.packages.uninstall_confirm');
@endphp
<div class="ag-preset-module-row" wire:key="preset-module-row-{{ $presetId }}-{{ $manifest->id }}">
    <div class="ag-preset-module-row__copy">
        <strong>{{ $manifest->name }}</strong>
        <span>{{ $manifest->description }}</span>
    </div>
    <span @class(['ag-badge', 'ag-badge--success' => $row['enabled'], 'ag-badge--warning' => $row['installed'] && ! $row['enabled'], 'ag-badge--muted' => ! $row['installed']])>
        {{ __($row['lifecycle']->labelKey()) }}
    </span>
    <div class="ag-preset-module-row__actions">
        @can('modules.manage')
            @if (! $row['on_disk'] && $row['compatible'])
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="installFromMonorepo('{{ $row['monorepo_key'] }}')">{{ __('admin.packages.actions.download_install') }}</button>
            @elseif (! $row['installed'] && $row['compatible'] && $row['on_disk'])
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="install('{{ $manifest->id }}')">{{ __('admin.modules.actions.install') }}</button>
            @elseif ($row['enabled'])
                <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="disable('{{ $manifest->id }}')">{{ __('admin.modules.actions.disable') }}</button>
                <button
                    type="button"
                    class="ag-btn ag-btn--ghost ag-btn--sm"
                    x-data
                    @click="if (confirm(@js($uninstallConfirm))) { $wire.uninstallPackage('{{ $manifest->id }}') }"
                >{{ __('admin.packages.actions.uninstall') }}</button>
            @elseif ($row['installed'] && $row['compatible'])
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="enable('{{ $manifest->id }}')">{{ __('admin.modules.actions.enable') }}</button>
                <button
                    type="button"
                    class="ag-btn ag-btn--ghost ag-btn--sm"
                    x-data
                    @click="if (confirm(@js($uninstallConfirm))) { $wire.uninstallPackage('{{ $manifest->id }}') }"
                >{{ __('admin.packages.actions.uninstall') }}</button>
            @endif
        @endcan
    </div>
</div>
