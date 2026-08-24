<section class="admin-panel">
    <div class="admin-panel__header">
        <div>
            <p class="ag-eyebrow">{{ __('admin.modules.setup_eyebrow') }}</p>
            <h2 class="admin-panel__title">{{ __('admin.modules.setup_title') }}</h2>
            <p class="ag-muted">{{ __('admin.modules.setup_lede_available') }}</p>
        </div>
    </div>

    @if ($availablePresets === [] && ($availableCustomPresetRow['moduleCount'] ?? 0) === 0)
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.modules.empty.available_presets_title') }}</p>
            <p class="ag-empty__text">{{ __('admin.modules.empty.available_presets_text') }}</p>
        </div>
    @else
        <div class="ag-setup-board ag-setup-board--available" role="group" aria-label="{{ __('admin.modules.presets_aria') }}">
            @foreach ($availablePresets as $row)
                @php
                    $preset = $row['preset'];
                    $moduleCount = count($row['modules']);
                @endphp
                <article
                    class="ag-setup-board__item"
                    wire:key="module-preset-{{ $preset->id }}"
                    x-data="{ open: false }"
                    x-bind:class="open ? 'is-open' : ''"
                >
                    <div class="ag-setup-board__header">
                        <div class="ag-setup-board__intro">
                            <span class="ag-setup-board__copy">
                                <span class="ag-setup-board__title-row">
                                    <strong class="ag-setup-board__title">{{ __($preset->labelKey) }}</strong>
                                </span>
                                <span class="ag-setup-board__lede">{{ __($preset->ledeKey) }}</span>
                            </span>
                        </div>

                        <div class="ag-setup-board__header-actions">
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
                            @endcan
                        </div>
                    </div>

                    @if ($moduleCount > 0)
                        <div class="ag-setup-board__panel" x-show="open" x-cloak>
                            <div class="ag-setup-board__chips" role="list">
                                @foreach ($row['modules'] as $module)
                                    <span class="ag-setup-board__chip" role="listitem" wire:key="preset-module-{{ $preset->id }}-{{ $module['id'] }}">
                                        {{ $module['name'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach

            @if ($availableCustomPresetRow)
                @php
                    $customPreset = $availableCustomPresetRow['preset'];
                    $customModuleCount = $availableCustomPresetRow['moduleCount'];
                @endphp
                <article
                    class="ag-setup-board__item ag-setup-board__item--custom-available"
                    wire:key="module-preset-custom-available"
                    x-data="{ open: false }"
                    x-bind:class="open ? 'is-open' : ''"
                >
                    <div class="ag-setup-board__header">
                        <div class="ag-setup-board__intro">
                            <span class="ag-setup-board__copy">
                                <span class="ag-setup-board__title-row">
                                    <strong class="ag-setup-board__title">{{ __($customPreset->labelKey) }}</strong>
                                </span>
                                <span class="ag-setup-board__lede">{{ __($customPreset->ledeKey) }}</span>
                            </span>
                        </div>

                        <div class="ag-setup-board__header-actions">
                            @if ($customModuleCount > 0)
                                <button
                                    type="button"
                                    class="ag-setup-board__expand"
                                    @click="open = ! open"
                                    :aria-expanded="open.toString()"
                                >
                                    <span>{{ __('admin.modules.view_modules', ['count' => $customModuleCount]) }}</span>
                                    <x-ag.icon name="chevron-down" :size="16" />
                                </button>
                            @endif
                            @can('modules.manage')
                                @if ($availableCustomPresetRow['canInstallAll'] ?? false)
                                    <button
                                        type="button"
                                        class="ag-btn ag-btn--primary ag-btn--sm"
                                        wire:click="installAllCustomModules"
                                        wire:loading.attr="disabled"
                                        wire:target="installAllCustomModules"
                                    >
                                        <span wire:loading.remove wire:target="installAllCustomModules">{{ __('admin.modules.install_all') }}</span>
                                        <span wire:loading wire:target="installAllCustomModules">{{ __('common.loading') }}</span>
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </div>

                    @if ($customModuleCount > 0)
                        <div class="ag-setup-board__panel" x-show="open" x-cloak>
                            @foreach ($availableCustomPresetRow['moduleGroups'] as $group => $moduleRows)
                                <div class="ag-setup-custom-available__group">
                                    <h4 class="ag-setup-board__group-title">{{ __('admin.modules.groups.'.$group) }}</h4>
                                    <div class="ag-setup-board__installed-modules">
                                        @foreach ($moduleRows as $moduleRow)
                                            @include('livewire.admin.partials.available-module-row', ['row' => $moduleRow])
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endif
        </div>
    @endif
</section>
