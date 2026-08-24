<div class="admin-page">
    <x-ag.page-header :heading="__('admin.modules.title')" :lede="__('admin.modules.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @include('livewire.admin.partials.package-tabs', ['active' => $tab, 'tabs' => $tabs])

    @if ($tab === 'installed')
        @include('livewire.admin.partials.package-group-grid', [
            'groups' => $installedGroups,
            'groupLabelPrefix' => 'admin.modules.groups.',
            'cardPartial' => 'livewire.admin.partials.module-card',
            'emptyTitle' => __('admin.modules.empty.installed_title'),
            'emptyText' => __('admin.modules.empty.installed_text'),
        ])
        <p class="ag-muted">{{ __('admin.modules.disable_preserves_data') }}</p>
        <p class="ag-muted">{{ __('admin.packages.uninstall_vs_purge') }}</p>
    @elseif ($tab === 'available')
        <form wire:submit="applyConfiguration" class="admin-panel ag-form">
            <div class="admin-panel__header">
                <div>
                    <p class="ag-eyebrow">{{ __('admin.modules.setup_eyebrow') }}</p>
                    <h2 class="admin-panel__title">{{ __('admin.modules.setup_title') }}</h2>
                    <p class="ag-muted">{{ __('admin.modules.setup_lede') }}</p>
                </div>
                <span class="ag-badge ag-badge--muted">{{ __('admin.modules.source_of_truth') }}</span>
            </div>

            <fieldset class="ag-preset-grid">
                <legend class="visually-hidden">{{ __('admin.modules.presets_aria') }}</legend>
                @foreach ($presets as $row)
                    @php $preset = $row['preset']; @endphp
                    <label class="ag-preset-card {{ $preset->isCustom ? 'ag-preset-card--custom' : '' }}" wire:key="module-preset-{{ $preset->id }}">
                        <input class="ag-preset-card__input" type="checkbox" value="{{ $preset->id }}" wire:model.live="selectedPresets">
                        <span class="ag-preset-card__body">
                            <span class="ag-preset-card__topline">
                                <strong class="ag-preset-card__title">{{ __($preset->labelKey) }}</strong>
                                <span class="ag-preset-card__check" aria-hidden="true"><x-ag.icon name="check" :size="15" /></span>
                            </span>
                            <span class="ag-preset-card__text">{{ __($preset->ledeKey) }}</span>
                            @if ($row['modules'] !== [])
                                <span class="ag-preset-card__modules">
                                    {{ __('admin.modules.enables') }}: {{ implode(' · ', $row['modules']) }}
                                </span>
                            @else
                                <span class="ag-preset-card__modules">{{ __('admin.modules.choose_individually') }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </fieldset>

            <div class="ag-form__actions">
                @can('modules.manage')
                    <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.modules.save_setup') }}</button>
                @endcan
            </div>
        </form>

        @include('livewire.admin.partials.package-group-grid', [
            'groups' => $availableGroups,
            'groupLabelPrefix' => 'admin.modules.groups.',
            'cardPartial' => 'livewire.admin.partials.module-card',
            'emptyTitle' => __('admin.modules.empty.available_title'),
            'emptyText' => __('admin.modules.empty.available_text'),
        ])
    @else
        <form wire:submit="applyConfiguration" class="admin-panel ag-form" x-data="{ openCustom: true }">
            <div class="admin-panel__header">
                <div>
                    <h2 class="admin-panel__title">{{ __('admin.modules.custom_title') }}</h2>
                    <p class="ag-muted">{{ __('admin.modules.custom_lede') }}</p>
                </div>
            </div>

            <section class="ag-module-picker" aria-labelledby="custom-modules-title">
                <button type="button" class="ag-module-picker__toggle" @click="openCustom = ! openCustom" :aria-expanded="openCustom.toString()">
                    <span>
                        <strong id="custom-modules-title">{{ __('admin.modules.custom_picker_title') }}</strong>
                        <span class="ag-muted">{{ __('admin.modules.custom_picker_lede') }}</span>
                    </span>
                    <x-ag.icon name="chevron-down" :size="18" />
                </button>
                <div x-show="openCustom" x-cloak class="ag-module-picker__body">
                    @foreach ($groups as $group => $modules)
                        <div class="ag-module-picker__group">
                            <h3>{{ __('admin.modules.groups.'.$group) }}</h3>
                            <div class="ag-module-picker__list">
                                @foreach ($modules as $row)
                                    @php $manifest = $row['manifest']; @endphp
                                    <label class="ag-module-row" wire:key="custom-module-{{ $manifest->id }}">
                                        <input type="checkbox" value="{{ $manifest->id }}" wire:model="customModuleIds" @disabled(! $row['compatible'] || ! $row['installed'])>
                                        <span class="ag-module-row__copy">
                                            <strong>{{ $manifest->name }}</strong>
                                            <span class="ag-muted">{{ $manifest->description }}</span>
                                        </span>
                                        <span @class(['ag-badge', 'ag-badge--success' => $row['enabled'], 'ag-badge--warning' => $row['installed'] && ! $row['enabled'], 'ag-badge--muted' => ! $row['installed']])>
                                            {{ __($row['lifecycle']->labelKey()) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div class="ag-form__actions">
                @can('modules.manage')
                    <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.modules.save_setup') }}</button>
                @endcan
            </div>
        </form>

        @can('modules.manage')
            @include('livewire.admin.partials.package-zip-form', ['kind' => 'module'])
            @include('livewire.admin.partials.package-install-form', ['kind' => 'module'])
        @endcan

        @include('livewire.admin.partials.package-group-grid', [
            'groups' => $groups,
            'groupLabelPrefix' => 'admin.modules.groups.',
            'cardPartial' => 'livewire.admin.partials.module-card',
            'emptyTitle' => __('admin.modules.empty.title'),
            'emptyText' => __('admin.modules.empty.text'),
        ])
    @endif

    @include('livewire.admin.partials.confirm-password-modal')
</div>
