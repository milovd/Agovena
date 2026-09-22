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
                </div>
                <form wire:submit.prevent="saveSettings" class="ag-modal-settings__form">
                    <div class="ag-modal-settings__body">
                        <div class="ag-modal-settings__fields">
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
                                <div class="ag-field{{ $settingType === 'payment_methods' ? ' ag-modal-settings__field--methods' : '' }}">
                                    <label class="ag-field__label" for="ext-setting-{{ $key }}">{{ __($settingLabel) }}</label>
                                    @if ($settingType === 'payment_methods')
                                        @if (! $settingsMethodTested)
                                            <p class="ag-field__hint">{{ __('admin.extensions.settings_methods_not_tested') }}</p>
                                        @else
                                            <div class="ag-payment-methods">
                                                @forelse ($settingsMethodOptions as $method)
                                                    @php
                                                        $methodId = (string) $method['id'];
                                                        $methodIcon = match (true) {
                                                            in_array($methodId, ['card'], true) => 'credit-card',
                                                            in_array($methodId, ['bancontact', 'blik', 'eps', 'ideal', 'pay_by_bank', 'sepa_debit', 'us_bank_account'], true) => 'landmark',
                                                            in_array($methodId, ['cashapp', 'klarna', 'link', 'paypal'], true) => 'wallet',
                                                            default => 'banknote',
                                                        };
                                                    @endphp
                                                    <label class="ag-payment-method" wire:key="settings-method-{{ $methodId }}">
                                                        <input class="ag-payment-method__input" type="checkbox" value="{{ $methodId }}" wire:model.live="settingsMethodSelections">
                                                        <span class="ag-payment-method__icon" aria-hidden="true">
                                                            @if ($method['icon'])
                                                                <img src="{{ $method['icon'] }}" alt="" width="24" height="24" loading="lazy">
                                                            @else
                                                                <x-ag.icon :name="$methodIcon" :size="20" class="ag-payment-method__svg" />
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
                        </div>
                    </div>
                    <div class="ag-modal__actions ag-modal-settings__actions">
                        <button type="button" class="ag-btn ag-btn--ghost" wire:click="closeSettings">{{ __('common.cancel') }}</button>
                        @if ($settingsHasHealth)
                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="testConnection('{{ $settingsExtensionId }}')" wire:loading.attr="disabled" wire:target="testConnection">
                                <span wire:loading.remove wire:target="testConnection">{{ __('admin.extensions.actions.health') }}</span>
                                <span wire:loading wire:target="testConnection">{{ __('admin.extensions.settings_test_loading') }}</span>
                            </button>
                        @endif
                        <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="testConnection">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @include('livewire.admin.partials.confirm-password-modal')
</div>
