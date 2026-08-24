<div class="admin-page">
    <x-ag.page-header :heading="__('admin.modules.title')" :lede="__('admin.modules.lede')">
        <p class="admin-page__note">{{ __('admin.modules.disable_preserves_data') }}</p>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @include('livewire.admin.partials.package-tabs', ['active' => $tab, 'tabs' => $tabs])

    @if ($tab === 'installed')
        <section class="admin-panel">
            <div class="admin-panel__header">
                <div>
                    <h2 class="admin-panel__title">{{ __('admin.modules.installed_setups_title') }}</h2>
                    <p class="ag-muted">{{ __('admin.modules.installed_setups_lede') }}</p>
                </div>
            </div>

            @if ($installedPresetRows === [] && ! $hasCustomModules)
                <div class="ag-empty" role="status">
                    <p class="ag-empty__title">{{ __('admin.modules.empty.installed_title') }}</p>
                    <p class="ag-empty__text">{{ __('admin.modules.empty.installed_text') }}</p>
                </div>
            @else
                @include('livewire.admin.partials.installed-preset-board', [
                    'installedPresetRows' => $installedPresetRows,
                    'hasCustomModules' => $hasCustomModules,
                    'customModuleRows' => $customModuleRows,
                    'customUninstallConfirm' => $customUninstallConfirm ?? null,
                ])
            @endif
        </section>
    @elseif ($tab === 'available')
        @include('livewire.admin.partials.preset-catalog', [
            'availablePresets' => $availablePresets,
            'availableCustomPresetRow' => $availableCustomPresetRow,
        ])
    @else
        @can('modules.manage')
            @include('livewire.admin.partials.package-zip-form', ['kind' => 'module'])
        @endcan
    @endif

    @include('livewire.admin.partials.confirm-password-modal')
</div>
