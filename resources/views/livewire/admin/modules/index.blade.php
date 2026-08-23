<div class="admin-page">
    <x-ag.page-header :heading="__('admin.modules.title')" :lede="__('admin.modules.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <form wire:submit="applyConfiguration" class="admin-panel ag-form" x-data="{ openPreset: null, openCustom: true }">
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

        <section class="ag-module-picker" aria-labelledby="custom-modules-title">
            <button type="button" class="ag-module-picker__toggle" @click="openCustom = ! openCustom" :aria-expanded="openCustom.toString()">
                <span>
                    <strong id="custom-modules-title">{{ __('admin.modules.custom_title') }}</strong>
                    <span class="ag-muted">{{ __('admin.modules.custom_lede') }}</span>
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
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.extensions.index') }}">{{ __('admin.modules.manage_extensions') }}</a>
        </div>
    </form>

    @foreach ($groups as $group => $modules)
        <section class="admin-panel">
            <h2 class="admin-panel__title">{{ __('admin.modules.groups.'.$group) }}</h2>
            <div class="ag-package-grid">
                @foreach ($modules as $row)
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
                                    <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="installFromMonorepo('{{ $row['monorepo_key'] }}')">{{ __('admin.packages.actions.download_install') }}</button>
                                @elseif (! $row['installed'] && $row['compatible'] && $row['on_disk'])
                                    <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="install('{{ $manifest->id }}')">{{ __('admin.modules.actions.install') }}</button>
                                @elseif ($row['enabled'])
                                    <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="disable('{{ $manifest->id }}')">{{ __('admin.modules.actions.disable') }}</button>
                                    @if ($row['lifecycle']->value === 'update_available')
                                        <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="updatePackage('{{ $manifest->id }}')">{{ __('admin.packages.actions.update') }}</button>
                                    @endif
                                @elseif ($row['installed'] && $row['compatible'])
                                    <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="enable('{{ $manifest->id }}')">{{ __('admin.modules.actions.enable') }}</button>
                                    <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="uninstallPackage('{{ $manifest->id }}')" wire:confirm="{{ __('admin.packages.uninstall_confirm') }}">{{ __('admin.packages.actions.uninstall') }}</button>
                                    @if ($row['can_purge'])
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="purgePackage('{{ $manifest->id }}')" wire:confirm="{{ __('admin.packages.purge_confirm') }}">{{ __('admin.packages.actions.purge') }}</button>
                                    @endif
                                @endif
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
    <p class="ag-muted">{{ __('admin.modules.disable_preserves_data') }}</p>
    @include('livewire.admin.partials.confirm-password-modal')
</div>
