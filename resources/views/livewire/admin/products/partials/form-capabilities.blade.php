{{-- Product form (edit mode): automation tab with capability presets. Part of livewire.admin.products.form. --}}
    <section id="product-tab-automation" class="ag-section" role="tabpanel" x-cloak x-show="activeTab === 'automation'" aria-labelledby="section-capabilities">
        <header class="ag-section__header">
        <h3 id="section-capabilities" class="ag-section__title">{{ __('admin.products.presets.title') }}</h3>
        <p class="ag-section__lede">{{ __('admin.products.presets.lede') }}</p>
    </header>
    <div class="ag-section__body">

        <div class="ag-preset-grid" role="group" aria-label="{{ __('admin.products.presets.aria') }}">
            @foreach (['simple', 'physical', 'digital', 'downloadable', 'subscription', 'hosted_service', 'event_ticket'] as $preset)
                @php
                    $requiredCapability = match ($preset) {
                        'physical' => 'physical',
                        'digital' => 'digital_secret',
                        'downloadable' => 'digital',
                        'subscription' => 'subscribable',
                        'hosted_service' => 'provisionable',
                        'event_ticket' => 'event_ticket',
                        default => null,
                    };
                    $isAvailable = $requiredCapability === null || in_array($requiredCapability, $availableCapabilityKeys, true);
                @endphp
                <button
                    type="button"
                    class="ag-preset {{ $sellingPreset === $preset ? 'is-active' : '' }}"
                    wire:click="applyPreset('{{ $preset }}')"
                    @disabled(! $isAvailable)
                >
                    <strong>{{ __('admin.products.presets.'.$preset.'.label') }}</strong>
                    <span>{{ __('admin.products.presets.'.$preset.'.help') }}</span>
                </button>
            @endforeach
        </div>

        @if (! empty($capabilityEnabled['digital_secret']))
            <div class="ag-preset-option ag-grid ag-grid--2" style="margin-top: 1rem;">
                <div class="ag-field">
                    <label class="ag-field__label" for="digital-secret-source">{{ __('admin.products.capabilities.digital_secret_source') }}</label>
                    <select id="digital-secret-source" class="ag-select" wire:model="digitalSecretSource">
                        <option value="pool">{{ __('admin.products.capabilities.digital_secret_source_pool') }}</option>
                        <option value="manual">{{ __('admin.products.capabilities.digital_secret_source_manual') }}</option>
                        <option value="provider">{{ __('admin.products.capabilities.digital_secret_source_provider') }}</option>
                    </select>
                    <p class="ag-field__hint">{{ __('admin.products.capabilities.digital_secret_source_hint') }}</p>
                </div>
            </div>
        @endif

        @if (in_array('provisionable', $availableCapabilityKeys, true))
            <div class="ag-preset-option">
                <x-ag.checkbox
                    id="hosted-service-subscription"
                    wire:model.live="hostedServiceSubscription"
                    :label="__('admin.products.presets.hosted_service.also_subscription')"
                />
            </div>
        @endif

        @if (($availableCapabilities ?? []) === [])
            <p class="ag-muted">{{ __('admin.products.capabilities.none') }}</p>
        @else
            <details class="ag-advanced">
                <summary class="ag-advanced__summary">{{ __('admin.products.presets.advanced') }}</summary>
                <p class="ag-field__hint">{{ __('admin.products.presets.advanced_help') }}</p>
                <div class="ag-stack ag-advanced__body">
                    @foreach ($availableCapabilities as $definition)
                        <div class="ag-field">
                            <x-ag.checkbox
                                :id="'capability-'.$definition->key"
                                wire:model="capabilityEnabled.{{ $definition->key }}"
                                :label="__($definition->label)"
                            />
                            @if ($definition->description !== '')
                                <p class="ag-field__hint">{{ __($definition->description) }}</p>
                            @endif
                        </div>
                    @endforeach

                    @if (! empty($capabilityEnabled['inventory']))
                        <div class="ag-field">
                            <label class="ag-field__label" for="stockQuantity">{{ __('admin.products.capabilities.stock_quantity') }}</label>
                            <input id="stockQuantity" class="ag-input" type="number" min="0" wire:model="stockQuantity">
                            <p class="ag-field__hint">{{ __('admin.products.capabilities.stock_hint') }}</p>
                        </div>
                    @endif

                    @if (! empty($capabilityEnabled['shippable']))
                        <div class="ag-field">
                            <label class="ag-field__label" for="weightGrams">{{ __('admin.products.capabilities.weight_grams') }}</label>
                            <input id="weightGrams" class="ag-input" type="number" min="0" wire:model="weightGrams">
                            <p class="ag-field__hint">{{ __('admin.products.capabilities.weight_hint') }}</p>
                        </div>
                    @endif

                    @if (! empty($capabilityEnabled['subscribable']))
                        <div class="ag-grid ag-grid--2">
                            <div class="ag-field">
                                <label class="ag-field__label" for="subscriptionInterval">{{ __('admin.products.capabilities.subscription_interval') }}</label>
                                <select id="subscriptionInterval" class="ag-select" wire:model="subscriptionInterval">
                                    <option value="day">{{ __('admin.products.capabilities.interval_day') }}</option>
                                    <option value="week">{{ __('admin.products.capabilities.interval_week') }}</option>
                                    <option value="month">{{ __('admin.products.capabilities.interval_month') }}</option>
                                    <option value="year">{{ __('admin.products.capabilities.interval_year') }}</option>
                                </select>
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label" for="subscriptionIntervalCount">{{ __('admin.products.capabilities.subscription_interval_count') }}</label>
                                <input id="subscriptionIntervalCount" class="ag-input" type="number" min="1" wire:model="subscriptionIntervalCount">
                                <p class="ag-field__hint">{{ __('admin.products.capabilities.subscription_interval_hint') }}</p>
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label" for="subscriptionTrialDays">{{ __('admin.products.capabilities.subscription_trial_days') }}</label>
                                <input id="subscriptionTrialDays" class="ag-input" type="number" min="0" wire:model="subscriptionTrialDays">
                                <p class="ag-field__hint">{{ __('admin.products.capabilities.subscription_trial_hint') }}</p>
                            </div>
                        </div>
                    @endif

                    @if (! empty($capabilityEnabled['domain_registration']))
                        <div class="ag-field ag-grid__span-2">
                            <h3 class="ag-section__title">{{ __('admin.products.capabilities.domain_unified') }}</h3>
                            <p class="ag-field__hint">{{ __('admin.products.capabilities.domain_unified_help') }}</p>
                        </div>
                        <div class="ag-grid ag-grid--2">
                            <div class="ag-field">
                                <label class="ag-field__label" for="domainRegistrarKey">{{ __('admin.products.capabilities.domain_registrar') }}</label>
                                <select id="domainRegistrarKey" class="ag-select" wire:model="domainRegistrarKey">
                                    <option value="">{{ __('admin.products.automation.select_provider') }}</option>
                                    @foreach ($domainRegistrars ?? [] as $provider)
                                        <option value="{{ $provider['key'] }}">{{ $provider['key'] }} ({{ implode(', ', $provider['capabilities']) }})</option>
                                    @endforeach
                                </select>
                                <p class="ag-field__hint">{{ __('admin.products.capabilities.domain_registrar_hint') }}</p>
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label" for="domainDnsProviderKey">{{ __('admin.products.capabilities.domain_dns_provider') }}</label>
                                <select id="domainDnsProviderKey" class="ag-select" wire:model="domainDnsProviderKey">
                                    <option value="">{{ __('admin.products.automation.select_provider') }}</option>
                                    @foreach ($domainDnsProviders ?? [] as $provider)
                                        <option value="{{ $provider['key'] }}">{{ $provider['key'] }} ({{ implode(', ', $provider['capabilities']) }})</option>
                                    @endforeach
                                </select>
                                <p class="ag-field__hint">{{ __('admin.products.capabilities.domain_dns_provider_hint') }}</p>
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label" for="domainName">{{ __('admin.products.capabilities.domain_name') }}</label>
                                <input id="domainName" class="ag-input" type="text" maxlength="253" wire:model="domainName">
                                <p class="ag-field__hint">{{ __('admin.products.capabilities.domain_name_hint') }}</p>
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label" for="domainYears">{{ __('admin.products.capabilities.domain_years') }}</label>
                                <input id="domainYears" class="ag-input" type="number" min="1" max="10" wire:model="domainYears">
                                <p class="ag-field__hint">{{ __('admin.products.capabilities.domain_years_hint') }}</p>
                            </div>
                            <div class="ag-field ag-grid__span-2">
                                <label class="ag-field__label" for="domainAllowedTlds">{{ __('admin.products.capabilities.domain_allowed_tlds') }}</label>
                                <input id="domainAllowedTlds" class="ag-input" type="text" wire:model="domainAllowedTlds" aria-describedby="domainAllowedTlds-hint">
                                <p id="domainAllowedTlds-hint" class="ag-field__hint">{{ __('admin.products.capabilities.domain_allowed_tlds_hint') }}</p>
                            </div>
                            <div class="ag-field">
                                <x-ag.checkbox
                                    id="domainAutoRenew"
                                    wire:model="domainAutoRenew"
                                    :label="__('admin.products.capabilities.domain_auto_renew')"
                                />
                            </div>
                        </div>
                    @endif

                    @if (! empty($capabilityEnabled['provisionable']))
                        <div class="ag-field">
                            <label class="ag-field__label" for="provisioningServerId">{{ __('admin.products.automation.server') }}</label>
                            <select id="provisioningServerId" class="ag-select" wire:model.live="provisioningServerId">
                                <option value="">{{ __('admin.products.automation.select_server') }}</option>
                                @foreach ($provisioningServers ?? [] as $server)
                                    <option value="{{ $server->id }}">{{ $server->name }} - {{ $server->provider_key }}</option>
                                @endforeach
                            </select>
                            <p class="ag-field__hint"><a href="{{ route('admin.provisioning.servers') }}">{{ __('admin.products.automation.manage_servers') }}</a></p>
                            @error('provisioningServerId') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        @foreach ($providerSettingDefinitions ?? [] as $definition)
                            <div class="ag-field">
                                <label class="ag-field__label" for="provider-setting-{{ $definition->key }}">{{ __($definition->label) }}</label>
                                @if ($definition->type === 'text')
                                    <textarea id="provider-setting-{{ $definition->key }}" class="ag-input" rows="4" wire:model="providerSettings.{{ $definition->key }}"></textarea>
                                @else
                                    <input id="provider-setting-{{ $definition->key }}" class="ag-input" type="text" wire:model="providerSettings.{{ $definition->key }}">
                                @endif
                                @if ($definition->help !== '')
                                    <p class="ag-field__hint">{{ __($definition->help) }}</p>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </details>
            <div class="ag-preset-save">
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="saveCapabilities">
                    {{ __('admin.products.capabilities.save') }}
                </button>
            </div>
        @endif
        @error('capability') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>
</section>
