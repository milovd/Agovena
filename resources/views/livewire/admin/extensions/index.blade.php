<div class="admin-page">
    <x-ag.page-header :heading="__('admin.extensions.title')" :lede="__('admin.extensions.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @can('extensions.manage')
        @include('livewire.admin.partials.package-install-form', ['kind' => 'extension'])
    @endcan

    <div class="ag-toolbar" style="margin-bottom: 1rem;">
        <select class="ag-select" wire:model.live="category" aria-label="{{ __('admin.extensions.filter_category') }}">
            <option value="">{{ __('admin.extensions.all_categories') }}</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->value }}">{{ __($cat->labelKey()) }}</option>
            @endforeach
        </select>
    </div>

    @php
        $flatExtensions = collect($groups)->flatten(1);
    @endphp

    @if ($groups === [])
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.extensions.empty.title') }}</p>
            <p class="ag-empty__text">{{ __('admin.extensions.empty.text') }}</p>
        </div>
    @else
        @foreach ($groups as $group => $extensions)
            <section class="admin-panel">
                <h2 class="admin-panel__title">{{ __('admin.extensions.categories.'.$group) }}</h2>
                <div class="ag-package-grid">
                    @foreach ($extensions as $row)
                        @php
                            /** @var \App\Agovena\Extensions\ExtensionManifest $manifest */
                            $manifest = $row['manifest'];
                        @endphp
                        <article class="ag-package-card" wire:key="extension-{{ $manifest->id }}">
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
                                <span class="ag-muted">{{ __('admin.extensions.column_author') }}: {{ $manifest->author }}</span>
                                @if ($row['compatible'])
                                    <span class="ag-muted">{{ __('admin.extensions.column_compatibility') }}: {{ $manifest->agovena }}</span>
                                @else
                                    <span class="ag-field__error">{{ $row['compatibility_error'] }}</span>
                                @endif
                            </div>
                            <div class="ag-package-card__actions">
                                @can('extensions.manage')
                                    @if (! $row['installed'])
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="install('{{ $manifest->id }}')" @disabled(! $row['compatible'])>
                                            {{ __('admin.extensions.actions.install') }}
                                        </button>
                                    @endif
                                    @if ($row['enabled'])
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="disable('{{ $manifest->id }}')">
                                            {{ __('admin.extensions.actions.disable') }}
                                        </button>
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="openSettings('{{ $manifest->id }}')">
                                            {{ __('admin.extensions.actions.settings') }}
                                        </button>
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="runHealth('{{ $manifest->id }}')">
                                            {{ __('admin.extensions.actions.health') }}
                                        </button>
                                    @elseif ($row['compatible'])
                                        <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="enable('{{ $manifest->id }}')">
                                            {{ __('admin.extensions.actions.enable') }}
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
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="purgePackage('{{ $manifest->id }}')" wire:confirm="{{ __('admin.packages.purge_confirm') }}">
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
        <p class="ag-muted">{{ __('admin.extensions.disable_preserves_data') }}</p>
        <p class="ag-muted">{{ __('admin.packages.uninstall_vs_purge') }}</p>
    @endif

    @if ($settingsExtensionId)
        <div class="ag-modal" role="dialog" aria-modal="true">
            <div class="ag-modal__backdrop" wire:click="closeSettings"></div>
            <div class="ag-modal__panel">
                <h3 class="ag-modal__title">{{ __('admin.extensions.settings_title', ['extension' => $settingsExtensionId]) }}</h3>
                <form wire:submit="saveSettings" class="ag-stack">
                    @php
                        $settingsManifest = $flatExtensions->first(
                            fn ($row) => $row['manifest']->id === $settingsExtensionId
                        )['manifest'] ?? null;
                    @endphp
                    @foreach ($settingsForm as $key => $value)
                        @php
                            $settingLabel = $key;
                            $settingType = 'string';
                            $settingSecret = false;
                            $settingHelp = '';
                            if ($settingsManifest !== null) {
                                foreach ($settingsManifest->settings as $definition) {
                                    if ($definition['key'] === $key) {
                                        $settingLabel = $definition['label'];
                                        $settingType = (string) ($definition['type'] ?? 'string');
                                        $settingSecret = (bool) ($definition['secret'] ?? false);
                                        $settingHelp = (string) ($definition['help'] ?? '');
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <div class="ag-field">
                            <label class="ag-field__label" for="ext-setting-{{ $key }}">{{ __($settingLabel) }}</label>
                            @if ($settingType === 'boolean')
                                <label class="ag-check">
                                    <input id="ext-setting-{{ $key }}" type="checkbox" wire:model="settingsForm.{{ $key }}" value="1">
                                    <span>{{ __($settingLabel) }}</span>
                                </label>
                            @elseif ($settingType === 'text')
                                <textarea id="ext-setting-{{ $key }}" class="ag-input" rows="4" wire:model="settingsForm.{{ $key }}"></textarea>
                            @else
                                <input
                                    id="ext-setting-{{ $key }}"
                                    class="ag-input"
                                    type="{{ $settingSecret ? 'password' : 'text' }}"
                                    wire:model="settingsForm.{{ $key }}"
                                    autocomplete="off"
                                    placeholder="{{ ($secretConfigured[$key] ?? false) ? __('admin.extensions.secret_placeholder') : '' }}"
                                >
                            @endif
                            @if ($settingSecret && ($secretConfigured[$key] ?? false))
                                <p class="ag-field__hint">{{ __('admin.extensions.secret_configured') }}</p>
                            @endif
                            @if ($settingHelp !== '')
                                <p class="ag-field__hint">{{ __($settingHelp) }}</p>
                            @endif
                        </div>
                    @endforeach
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--ghost" wire:click="closeSettings">{{ __('common.cancel') }}</button>
                        <button type="submit" class="ag-btn ag-btn--primary">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @include('livewire.admin.partials.confirm-password-modal')
</div>
