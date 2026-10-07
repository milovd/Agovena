@php
    $manifest = $row['manifest'];
@endphp
<div class="ag-preset-module-row" wire:key="available-module-row-{{ $manifest->id }}">
    <div class="ag-preset-module-row__copy">
        <strong>{{ $manifest->name }}</strong>
        <span>{{ $manifest->description }}</span>
    </div>
    <span @class([
        'ag-badge',
        'ag-badge--success' => $row['enabled'],
        'ag-badge--warning' => $row['installed'] && ! $row['enabled'],
        'ag-badge--muted' => ! $row['installed'] && ($row['compatible'] ?? false),
        'ag-badge--danger' => ! ($row['compatible'] ?? false),
    ])>
        {{ __($row['lifecycle']->labelKey()) }}
    </span>
    <div class="ag-preset-module-row__actions">
        @can('modules.manage')
            @if ($row['enabled'])
                {{-- Active on this store; manage under Installed. --}}
            @elseif (! ($row['compatible'] ?? false))
                {{-- Incompatible modules are listed for visibility only. --}}
            @elseif (! $row['on_disk'] && ($row['monorepo_key'] ?? null))
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="installFromMonorepo('{{ $row['monorepo_key'] }}')">{{ __('admin.packages.actions.download_install') }}</button>
            @elseif (! $row['installed'] && $row['on_disk'])
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="install('{{ $manifest->id }}')" wire:loading.attr="disabled" wire:target="install">{{ __('admin.modules.actions.install') }}</button>
            @elseif ($row['installed'])
                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="installCustomModule('{{ $manifest->id }}')" wire:loading.attr="disabled" wire:target="installCustomModule">{{ __('admin.modules.install_module') }}</button>
            @endif
        @endcan
    </div>
</div>
