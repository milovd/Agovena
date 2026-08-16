<div class="admin-page">
    <x-ag.page-header :heading="__('admin.modules.title')" :lede="__('admin.modules.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @can('modules.manage')
        @include('livewire.admin.partials.package-install-form', ['kind' => 'module'])
    @endcan

    @if ($groups === [])
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.modules.empty.title') }}</p>
            <p class="ag-empty__text">{{ __('admin.modules.empty.text') }}</p>
        </div>
    @else
        @foreach ($groups as $group => $modules)
            <section class="admin-panel">
                <h2 class="admin-panel__title">{{ __('admin.modules.groups.'.$group) }}</h2>
                <div class="ag-package-grid">
                    @foreach ($modules as $row)
                        @php
                            /** @var \App\Agovena\Modules\ModuleManifest $manifest */
                            $manifest = $row['manifest'];
                        @endphp
                        <article class="ag-package-card" wire:key="module-{{ $manifest->id }}">
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
                            <div class="ag-package-card__meta">
                                <span class="ag-muted">{{ __('admin.packages.column_source') }}: {{ __('admin.packages.source.'.$row['source']->value) }}</span>
                                @if ($manifest->dependencies !== [])
                                    <span class="ag-muted">{{ __('admin.modules.column_dependencies') }}: {{ implode(', ', $manifest->dependencies) }}</span>
                                @endif
                                @if (! $row['compatible'])
                                    <span class="ag-field__error">{{ $row['compatibility_error'] }}</span>
                                @endif
                            </div>
                            <div class="ag-package-card__actions">
                                @can('modules.manage')
                                    @if (! $row['installed'] && $row['compatible'])
                                        <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="install('{{ $manifest->id }}')">
                                            {{ __('admin.modules.actions.install') }}
                                        </button>
                                    @endif
                                    @if ($row['enabled'])
                                        <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="disable('{{ $manifest->id }}')">
                                            {{ __('admin.modules.actions.disable') }}
                                        </button>
                                    @elseif ($row['compatible'])
                                        <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="enable('{{ $manifest->id }}')">
                                            {{ __('admin.modules.actions.enable') }}
                                        </button>
                                    @endif
                                    @if ($row['lifecycle']->value === 'update_available')
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="updatePackage('{{ $manifest->id }}')">
                                            {{ __('admin.packages.actions.update') }}
                                        </button>
                                    @endif
                                    @if ($row['installed'] && ! $row['enabled'])
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="uninstallPackage('{{ $manifest->id }}')">
                                            {{ __('admin.packages.actions.uninstall') }}
                                        </button>
                                    @endif
                                    @if ($row['can_purge'])
                                        <button type="button" class="ag-btn ag-btn--danger ag-btn--sm" wire:click="purgePackage('{{ $manifest->id }}')" wire:confirm="{{ __('admin.packages.purge_confirm') }}">
                                            {{ __('admin.packages.actions.purge') }}
                                        </button>
                                    @endif
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
        <p class="ag-muted">{{ __('admin.modules.disable_preserves_data') }}</p>
        <p class="ag-muted">{{ __('admin.packages.uninstall_vs_purge') }}</p>
    @endif
    @include('livewire.admin.partials.confirm-password-modal')
</div>
