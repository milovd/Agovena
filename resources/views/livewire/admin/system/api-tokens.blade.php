<div class="admin-page">
    <x-ag.page-header :heading="__('admin.api_tokens.title')" :lede="__('admin.api_tokens.lede')">
        <x-slot:actions>
            @if ($showTokenForm)
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelTokenForm">
                    {{ __('admin.api_tokens.cancel') }}
                </button>
            @else
                @can('api.tokens')
                    <button type="button" class="ag-btn ag-btn--primary" wire:click="create">
                        {{ __('admin.api_tokens.add') }}
                    </button>
                @endcan
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if ($plainTextToken)
        <div class="ag-alert ag-alert--warning" role="status">
            <p class="ag-alert__title">{{ __('admin.api_tokens.secret') }}</p>
            <p class="ag-alert__text">{{ __('admin.api_tokens.copy_once') }}</p>
            <pre class="ag-code" tabindex="0"><code>{{ $plainTextToken }}</code></pre>
        </div>
    @endif

    @if ($showTokenForm)
        <form class="ag-form ag-form--product ag-api-token-editor" wire:submit="saveToken" novalidate>
            <section class="ag-section" aria-labelledby="api-token-details-title">
                <header class="ag-section__header">
                    <h2 id="api-token-details-title" class="ag-section__title">
                        {{ $editingTokenId ? __('admin.api_tokens.edit') : __('admin.api_tokens.new') }}
                    </h2>
                    <p class="ag-section__lede">
                        {{ $editingTokenId ? __('admin.api_tokens.edit_lede') : __('admin.api_tokens.create_lede') }}
                    </p>
                </header>
                <div class="ag-section__body">
                    <div class="ag-field">
                        <label class="ag-field__label" for="token-name">{{ __('admin.api_tokens.name') }}</label>
                        <input
                            id="token-name"
                            class="ag-input"
                            type="text"
                            wire:model="tokenName"
                            required
                            autocomplete="off"
                            aria-describedby="token-name-help"
                        >
                        <p id="token-name-help" class="ag-field__hint">{{ __('admin.api_tokens.name_help') }}</p>
                        @error('tokenName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="ag-section" aria-labelledby="api-token-access-title">
                <header class="ag-section__header">
                    <h2 id="api-token-access-title" class="ag-section__title">{{ __('admin.api_tokens.access_heading') }}</h2>
                    <p class="ag-section__lede">{{ __('admin.api_tokens.access_lede') }}</p>
                </header>
                <div class="ag-section__body">
                    <label class="ag-api-token-permission ag-api-token-permission--all">
                        <input type="checkbox" value="*" wire:model="selectedAbilities">
                        <span class="ag-api-token-permission__copy">
                            <strong>{{ __('admin.api_tokens.full_access') }}</strong>
                            <small>{{ __('admin.api_tokens.full_access_help') }}</small>
                        </span>
                    </label>

                    <div class="ag-api-token-permission-groups">
                        @foreach ($abilityGroups as $group => $abilities)
                            <fieldset class="ag-api-token-permission-group">
                                <legend class="ag-api-token-permission-group__title">{{ __('admin.api_tokens.ability_groups.'.$group) }}</legend>
                                <div class="ag-api-token-permission-group__items">
                                    @foreach ($abilities as $ability)
                                        <label class="ag-api-token-permission">
                                            <input type="checkbox" value="{{ $ability['key'] }}" wire:model="selectedAbilities">
                                            <span class="ag-api-token-permission__copy">
                                                <strong>{{ __($ability['label']) }}</strong>
                                                <small>{{ __($ability['description']) }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    @error('selectedAbilities') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    @error('selectedAbilities.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            </section>

            <section class="ag-section" aria-labelledby="api-token-ip-title">
                <header class="ag-section__header">
                    <h2 id="api-token-ip-title" class="ag-section__title">{{ __('admin.api_tokens.ip_heading') }}</h2>
                    <p class="ag-section__lede">{{ __('admin.api_tokens.ip_lede') }}</p>
                </header>
                <div class="ag-section__body">
                    <div class="ag-field">
                        <label class="ag-field__label" for="token-ip-allowlist">{{ __('admin.api_tokens.ip_label') }}</label>
                        <textarea
                            id="token-ip-allowlist"
                            class="ag-input ag-input--area"
                            rows="5"
                            wire:model="tokenIpAllowlist"
                            placeholder="203.0.113.10&#10;2001:db8::1"
                            spellcheck="false"
                            aria-describedby="token-ip-help"
                        ></textarea>
                        <p id="token-ip-help" class="ag-field__help">{{ __('admin.api_tokens.ip_help') }}</p>
                        @error('tokenIpAllowlist') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">
                    {{ $editingTokenId ? __('admin.api_tokens.save') : __('admin.api_tokens.create') }}
                </button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelTokenForm">
                    {{ __('common.cancel') }}
                </button>
            </div>
        </form>
    @endif

    <section class="ag-section ag-api-token-list" aria-labelledby="api-token-list-title">
        <header class="ag-section__header">
            <h2 id="api-token-list-title" class="ag-section__title">{{ __('admin.api_tokens.list_heading') }}</h2>
            <p class="ag-section__lede">{{ __('admin.api_tokens.list_lede') }}</p>
        </header>
        <div class="ag-section__body">
            <div class="ag-table-wrap">
                <table class="ag-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.api_tokens.name') }}</th>
                            <th scope="col">{{ __('admin.api_tokens.access') }}</th>
                            <th scope="col">{{ __('admin.api_tokens.ip_label') }}</th>
                            <th scope="col">{{ __('admin.api_tokens.last_used') }}</th>
                            <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tokens as $token)
                            @php
                                $tokenAbilities = is_array($token->abilities) ? $token->abilities : [];
                                $tokenIpAllowlist = is_array($token->ip_allowlist) ? $token->ip_allowlist : [];
                                $hasFullAccess = in_array('*', $tokenAbilities, true);
                            @endphp
                            <tr wire:key="pat-{{ $token->id }}">
                                <td>
                                    <div class="ag-table__primary">
                                        <span class="ag-table__name">{{ $token->name }}</span>
                                        <span class="ag-muted">{{ __('admin.api_tokens.ability_all') }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($hasFullAccess)
                                        <span class="ag-badge ag-badge--success">{{ __('admin.api_tokens.full_access') }}</span>
                                    @else
                                        <span class="ag-muted">{{ trans_choice('admin.api_tokens.access_count', count($tokenAbilities), ['count' => count($tokenAbilities)]) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($tokenIpAllowlist === [])
                                        <span class="ag-muted">{{ __('admin.api_tokens.ip_all') }}</span>
                                    @else
                                        <span class="ag-muted">{{ __('admin.api_tokens.ip_restricted', ['count' => count($tokenIpAllowlist)]) }}</span>
                                    @endif
                                </td>
                                <td>{{ $token->last_used_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('admin.api_tokens.never') }}</td>
                                <td class="ag-table__actions">
                                    <div class="ag-row-actions">
                                        <button
                                            type="button"
                                            class="ag-icon-btn"
                                            wire:click="edit({{ $token->id }})"
                                            title="{{ __('common.edit') }}"
                                            aria-label="{{ __('common.edit') }} {{ $token->name }}"
                                        >
                                            <x-ag.icon name="pencil" :size="16" />
                                        </button>
                                        <button
                                            type="button"
                                            class="ag-btn ag-btn--ghost ag-btn--sm"
                                            wire:click="revokeToken({{ $token->id }})"
                                            wire:confirm="{{ __('admin.api_tokens.revoke_confirm') }}"
                                        >{{ __('admin.api_tokens.revoke') }}</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">{{ __('admin.api_tokens.empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="ag-section" aria-labelledby="api-ip-policy-title">
        <header class="ag-section__header">
            <h2 id="api-ip-policy-title" class="ag-section__title">{{ __('admin.api_tokens.ip_allowlist_heading') }}</h2>
            <p class="ag-section__lede">{{ __('admin.api_tokens.ip_allowlist_lede') }}</p>
        </header>
        <div class="ag-section__body">
            <form class="ag-form" wire:submit="saveIpAllowlist">
                <div class="ag-field">
                    <label class="ag-field__label" for="api-ip-allowlist">{{ __('admin.api_tokens.ip_allowlist_label') }}</label>
                    <textarea
                        id="api-ip-allowlist"
                        class="ag-input ag-input--area"
                        rows="5"
                        wire:model="apiIpAllowlist"
                        placeholder="203.0.113.10&#10;2001:db8::1"
                        spellcheck="false"
                    ></textarea>
                    <p class="ag-field__help">{{ __('admin.api_tokens.ip_allowlist_help') }}</p>
                    @error('apiIpAllowlist') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-form__actions">
                    <button class="ag-btn ag-btn--secondary" type="submit" wire:loading.attr="disabled">
                        {{ __('admin.api_tokens.ip_allowlist_save') }}
                    </button>
                </div>
            </form>
        </div>
    </section>

    @include('livewire.admin.partials.confirm-password-modal')
</div>