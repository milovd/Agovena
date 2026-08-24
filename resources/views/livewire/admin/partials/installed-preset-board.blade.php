<section class="ag-setup-board ag-setup-board--installed" aria-label="{{ __('admin.modules.installed_setups_aria') }}">
    @foreach ($installedPresetRows as $row)
        @php
            $preset = $row['preset'];
            $moduleCount = count($row['moduleRows']);
        @endphp
        <article
            class="ag-setup-board__item ag-setup-board__item--installed"
            wire:key="installed-preset-{{ $preset->id }}"
            x-data="{ open: false }"
            x-bind:class="open ? 'is-open' : ''"
        >
            <div class="ag-setup-board__header">
                <div class="ag-setup-board__installed-main">
                    <span class="ag-setup-board__copy">
                        <span class="ag-setup-board__title-row">
                            <strong class="ag-setup-board__title">{{ __($preset->labelKey) }}</strong>
                            <span @class([
                                'ag-badge',
                                'ag-badge--success' => $row['status'] === 'active',
                                'ag-badge--warning' => $row['status'] === 'partial',
                                'ag-badge--muted' => $row['status'] === 'missing',
                            ])>{{ __($row['statusLabel']) }}</span>
                        </span>
                        <span class="ag-setup-board__lede">{{ __($preset->ledeKey) }}</span>
                    </span>
                </div>

                <div class="ag-setup-board__installed-actions">
                    @if ($moduleCount > 0)
                        <button
                            type="button"
                            class="ag-setup-board__expand"
                            @click="open = ! open"
                            :aria-expanded="open.toString()"
                        >
                            <span>{{ __('admin.modules.view_modules', ['count' => $moduleCount]) }}</span>
                            <x-ag.icon name="chevron-down" :size="16" />
                        </button>
                    @endif
                    @can('modules.manage')
                        @if ($row['canInstallSetup'])
                            <button
                                type="button"
                                class="ag-btn ag-btn--primary ag-btn--sm"
                                wire:click="installPreset('{{ $preset->id }}')"
                                wire:loading.attr="disabled"
                                wire:target="installPreset"
                            >
                                <span wire:loading.remove wire:target="installPreset">{{ __('admin.modules.install_all') }}</span>
                                <span wire:loading wire:target="installPreset">{{ __('common.loading') }}</span>
                            </button>
                        @endif
                        <button
                            type="button"
                            class="ag-btn ag-btn--ghost ag-btn--sm"
                            x-data
                            @click="if (confirm(@js($row['uninstallConfirm']))) { $wire.uninstallPreset('{{ $preset->id }}') }"
                            wire:loading.attr="disabled"
                            wire:target="uninstallPreset"
                        >{{ __('admin.modules.uninstall_setup') }}</button>
                    @endcan
                </div>
            </div>

            @if ($moduleCount > 0)
                <div class="ag-setup-board__panel" x-show="open" x-cloak>
                    <div class="ag-setup-board__installed-modules">
                        @foreach ($row['moduleRows'] as $moduleRow)
                            @include('livewire.admin.partials.preset-module-row', [
                                'row' => $moduleRow,
                                'presetId' => $preset->id,
                                'otherPresetLabels' => $moduleRow['otherPresetLabels'] ?? [],
                            ])
                        @endforeach
                    </div>
                </div>
            @endif
        </article>
    @endforeach

    @if ($hasCustomModules)
        <article class="ag-setup-board__item ag-setup-board__item--installed" wire:key="installed-preset-custom">
            <div class="ag-setup-board__header">
                <div class="ag-setup-board__installed-main">
                    <span class="ag-setup-board__copy">
                        <span class="ag-setup-board__title-row">
                            <strong class="ag-setup-board__title">{{ __('admin.store_presets.custom') }}</strong>
                            <span class="ag-badge ag-badge--success">{{ __('admin.modules.status.enabled') }}</span>
                        </span>
                        <span class="ag-setup-board__lede">{{ __('admin.store_presets.custom_lede') }}</span>
                    </span>
                </div>
                @can('modules.manage')
                    @if ($customUninstallConfirm)
                        <div class="ag-setup-board__installed-actions">
                            <button
                                type="button"
                                class="ag-btn ag-btn--ghost ag-btn--sm"
                                x-data
                                @click="if (confirm(@js($customUninstallConfirm))) { $wire.uninstallPreset('custom') }"
                                wire:loading.attr="disabled"
                                wire:target="uninstallPreset"
                            >{{ __('admin.modules.uninstall_setup') }}</button>
                        </div>
                    @endif
                @endcan
            </div>
            <div class="ag-setup-board__panel">
                <div class="ag-setup-board__installed-modules">
                    @foreach ($customModuleRows as $moduleRow)
                        @include('livewire.admin.partials.preset-module-row', [
                            'row' => $moduleRow,
                            'presetId' => 'custom',
                            'otherPresetLabels' => $moduleRow['otherPresetLabels'] ?? [],
                        ])
                    @endforeach
                </div>
            </div>
        </article>
    @endif
</section>
