<div class="admin-page">
    <x-ag.page-header :heading="__('admin.extensions.title')" :lede="__('admin.extensions.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @include('livewire.admin.partials.package-tabs', ['active' => $tab, 'tabs' => $tabs])

    @if ($tab === 'installed')
        <div class="ag-toolbar" style="margin-bottom: 1rem;">
            <select class="ag-select" wire:model.live="category" aria-label="{{ __('admin.extensions.filter_category') }}">
                <option value="">{{ __('admin.extensions.all_categories') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->value }}">{{ __($cat->labelKey()) }}</option>
                @endforeach
            </select>
        </div>
        @include('livewire.admin.partials.package-group-grid', [
            'groups' => $installedGroups,
            'groupLabelPrefix' => 'admin.extensions.categories.',
            'cardPartial' => 'livewire.admin.partials.extension-card',
            'emptyTitle' => __('admin.extensions.empty.installed_title'),
            'emptyText' => __('admin.extensions.empty.installed_text'),
        ])
        <p class="ag-muted">{{ __('admin.extensions.disable_preserves_data') }}</p>
        <p class="ag-muted">{{ __('admin.packages.uninstall_vs_purge') }}</p>
    @elseif ($tab === 'available')
        <div class="ag-toolbar" style="margin-bottom: 1rem;">
            <select class="ag-select" wire:model.live="category" aria-label="{{ __('admin.extensions.filter_category') }}">
                <option value="">{{ __('admin.extensions.all_categories') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->value }}">{{ __($cat->labelKey()) }}</option>
                @endforeach
            </select>
        </div>
        @include('livewire.admin.partials.package-group-grid', [
            'groups' => $availableGroups,
            'groupLabelPrefix' => 'admin.extensions.categories.',
            'cardPartial' => 'livewire.admin.partials.extension-card',
            'emptyTitle' => __('admin.extensions.empty.available_title'),
            'emptyText' => __('admin.extensions.empty.available_text'),
        ])
    @else
        @can('extensions.manage')
            @include('livewire.admin.partials.package-zip-form', ['kind' => 'extension'])
        @endcan
    @endif

    @php
        $flatExtensions = collect($groups)->flatten(1);
    @endphp

    @if ($settingsExtensionId)
        @php
            $settingsManifest = $flatExtensions->first(
                fn ($row) => $row['manifest']->id === $settingsExtensionId
            )['manifest'] ?? null;
        @endphp
        <div class="ag-modal ag-modal--settings" role="dialog" aria-modal="true" aria-labelledby="extension-settings-title">
            <div class="ag-modal__backdrop" wire:click="closeSettings"></div>
            <div class="ag-modal__panel ag-modal__panel--settings">
                <div class="ag-modal-settings__header">
                    <h3 id="extension-settings-title" class="ag-modal__title">{{ __('admin.extensions.settings_title', ['extension' => $settingsExtensionId]) }}</h3>
                    <p class="ag-modal-settings__intro">{{ __('admin.extensions.settings_intro') }}</p>
                    @if ($settingsConnectionState === 'success')
                        <p class="ag-alert ag-alert--success ag-modal-settings__connection" role="status">{{ $settingsConnectionMessage }}</p>
                    @elseif ($settingsConnectionState === 'error')
                        <p class="ag-alert ag-alert--danger ag-modal-settings__connection" role="alert">{{ $settingsConnectionMessage }}</p>
                    @endif
                </div>
                <form wire:submit.prevent="saveSettings" class="ag-modal-settings__form">
                    <div class="ag-modal-settings__body">
                        <div class="ag-modal-settings__fields">
                            @foreach ($settingsForm as $key => $value)
                                @php
                                    $settingLabel = $key;
                                    $settingType = 'string';
                                    $settingSecret = false;
                                    $settingConnection = false;
                                    $settingHelp = '';
                                    if ($settingsManifest !== null) {
                                        foreach ($settingsManifest->settings as $definition) {
                                            if ($definition['key'] === $key) {
                                                $settingLabel = $definition['label'];
                                                $settingType = (string) ($definition['type'] ?? 'string');
                                                $settingSecret = (bool) ($definition['secret'] ?? false);
                                                $settingConnection = (bool) ($definition['connection'] ?? false)
                                                    || (bool) ($definition['connection_context'] ?? false);
                                                $settingHelp = (string) ($definition['help'] ?? '');
                                                break;
                                            }
                                        }
                                    }
                                @endphp
                                <div class="ag-field{{ $settingType === 'payment_methods' ? ' ag-modal-settings__field--methods' : '' }}">
                                    @if ($settingType === 'payment_methods')
                                        <div class="ag-modal-settings__methods-heading">
                                            <div>
                                                <label class="ag-field__label" for="ext-setting-{{ $key }}">{{ __($settingLabel) }}</label>
                                                @if (! $settingsMethodsLoaded)
                                                    <p class="ag-field__hint">{{ __('admin.extensions.settings_methods_not_loaded') }}</p>
                                                @endif
                                            </div>
                                            <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="refreshPaymentMethods" wire:loading.attr="disabled" wire:target="refreshPaymentMethods">
                                                <x-ag.icon name="repeat" :size="16" />
                                                <span wire:loading.remove wire:target="refreshPaymentMethods">{{ __('admin.extensions.actions.refresh_methods') }}</span>
                                                <span wire:loading wire:target="refreshPaymentMethods">{{ __('admin.extensions.settings_refresh_loading') }}</span>
                                            </button>
                                        </div>
                                        @if (! $settingsMethodsLoaded)
                                            <p class="ag-field__hint">{{ __('admin.extensions.settings_credentials_required') }}</p>
                                        @else
                                            <div class="ag-payment-methods">
                                                @forelse ($settingsMethodOptions as $method)
                                                    @php
                                                        $methodId = (string) $method['id'];
                                                        $methodIconValue = is_string($method['icon'] ?? null) ? $method['icon'] : '';
                                                        $methodIcon = str_starts_with($methodIconValue, 'ag:')
                                                            ? substr($methodIconValue, 3)
                                                            : null;
                                                        $methodRemoteIcon = filter_var($methodIconValue, FILTER_VALIDATE_URL) ? $methodIconValue : null;
                                                    @endphp
                                                    <label class="ag-payment-method" wire:key="settings-method-{{ $methodId }}">
                                                        <input class="ag-payment-method__input" type="checkbox" value="{{ $methodId }}" wire:model.live="settingsMethodSelections">
                                                        <span class="ag-payment-method__icon" aria-hidden="true">
                                                            @if ($methodRemoteIcon)
                                                                <img class="ag-payment-method__provider-icon" src="{{ $methodRemoteIcon }}" alt="" width="32" height="24" loading="lazy">
                                                            @elseif ($methodIcon)
                                                                <x-ag.icon :name="$methodIcon" :size="32" class="ag-payment-method__svg" />
                                                            @else
                                                                <x-ag.icon name="payment-bank" :size="20" class="ag-payment-method__svg" />
                                                            @endif
                                                        </span>
                                                        <span class="ag-payment-method__label">{{ __($method['label']) }}</span>
                                                    </label>
                                                @empty
                                                    <p class="ag-field__hint">{{ __('admin.extensions.settings_methods_unavailable') }}</p>
                                                @endforelse
                                            </div>
                                        @endif
                                    @elseif ($settingType === 'boolean')
                                        <label class="ag-field__label" for="ext-setting-{{ $key }}">{{ __($settingLabel) }}</label>
                                        <label class="ag-check">
                                            <input id="ext-setting-{{ $key }}" type="checkbox" wire:model{{ $settingConnection ? '.live' : '' }}="settingsForm.{{ $key }}" value="1">
                                            <span>{{ __($settingLabel) }}</span>
                                        </label>
                                    @elseif ($settingType === 'text')
                                        <label class="ag-field__label" for="ext-setting-{{ $key }}">{{ __($settingLabel) }}</label>
                                        <textarea id="ext-setting-{{ $key }}" class="ag-input" rows="4" wire:model="settingsForm.{{ $key }}"></textarea>
                                    @else
                                        <label class="ag-field__label" for="ext-setting-{{ $key }}">{{ __($settingLabel) }}</label>
                                        <input
                                            id="ext-setting-{{ $key }}"
                                            class="ag-input"
                                            type="{{ $settingSecret ? 'password' : 'text' }}"
                                            wire:model{{ $settingConnection ? '.live.debounce.500ms' : '' }}="settingsForm.{{ $key }}"
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
                        </div>
                    </div>
                    <div class="ag-modal__actions ag-modal-settings__actions">
                        <button type="button" class="ag-btn ag-btn--ghost" wire:click="closeSettings">{{ __('common.cancel') }}</button>
                        <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="saveSettings">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @include('livewire.admin.partials.confirm-password-modal')
</div>
