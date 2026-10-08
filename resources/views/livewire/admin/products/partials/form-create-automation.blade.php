{{-- Product form (create mode): automation tab for provisioning setup. Part of livewire.admin.products.form. --}}
<div id="product-tab-automation" role="tabpanel" x-cloak x-show="activeTab === 'automation'">
    <section class="ag-section" aria-labelledby="section-create-automation">
        <header class="ag-section__header">
            <h3 id="section-create-automation" class="ag-section__title">{{ __('admin.products.automation.title') }}</h3>
            <p class="ag-section__lede">{{ __('admin.products.automation.lede') }}</p>
        </header>
        <div class="ag-section__body">
            <x-ag.checkbox
                id="configure-provisioning"
                wire:model.live="configureProvisioning"
                :label="__('admin.products.automation.enable_provisioning')"
            />
            @if ($configureProvisioning)
                <div class="ag-provider-settings">
                    <div class="ag-field">
                        <label class="ag-field__label" for="create-provisioning-server">{{ __('admin.products.automation.server') }}</label>
                        <select id="create-provisioning-server" class="ag-select" wire:model.live="provisioningServerId" required>
                            <option value="">{{ __('admin.products.automation.select_server') }}</option>
                            @foreach ($provisioningServers ?? [] as $server)
                                <option value="{{ $server->id }}">{{ in_array($server->id, $unconfiguredServerIds ?? [], true) ? __('admin.provider_status.option_not_configured', ['label' => $server->name]) : $server->name }}</option>
                            @endforeach
                        </select>
                        @if (($provisioningServers ?? collect())->isEmpty())
                            <p class="ag-field__hint"><a href="{{ route('admin.provisioning.servers') }}">{{ __('admin.products.automation.configure_server_first') }}</a></p>
                        @elseif (($unconfiguredServerIds ?? []) !== [])
                            <p class="ag-field__hint">{{ __('admin.provider_status.picker_hint') }} <a href="{{ route('admin.provisioning.servers') }}">{{ __('admin.products.automation.manage_servers') }}</a></p>
                        @endif
                        @error('provisioningServerId') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-alert ag-alert--info" role="status">
                        <div class="ag-alert__body">
                            <p class="ag-alert__title">{{ $providerKey !== '' ? ucfirst($providerKey) : __('admin.products.automation.provider') }}</p>
                            <p class="ag-alert__text">{{ __('admin.products.automation.provider_hint') }}</p>
                        </div>
                    </div>
                    <div class="ag-grid ag-grid--2">
                        @foreach ($providerSettingDefinitions ?? [] as $definition)
                            <div class="ag-field {{ $definition->type === 'text' ? 'ag-grid__span-2' : '' }}">
                                <label class="ag-field__label" for="create-provider-setting-{{ $definition->key }}">{{ __($definition->label) }}</label>
                                @if ($definition->type === 'text')
                                    <textarea id="create-provider-setting-{{ $definition->key }}" class="ag-input" rows="5" wire:model="providerSettings.{{ $definition->key }}"></textarea>
                                @else
                                    <input id="create-provider-setting-{{ $definition->key }}" class="ag-input" type="text" wire:model="providerSettings.{{ $definition->key }}" @required($definition->required)>
                                @endif
                                @if ($definition->help !== '')
                                    <p class="ag-field__hint">{{ __($definition->help) }}</p>
                                @endif
                                @error('providerSettings.'.$definition->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
