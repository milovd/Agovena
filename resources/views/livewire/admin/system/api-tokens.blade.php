<div class="admin-page">
    <x-ag.page-header :heading="__('admin.api_tokens.title')" :lede="__('admin.api_tokens.lede')" />

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

    <div class="ag-form-stack">
        <x-ag.card>
            <x-ag.card.header>
                <x-ag.card.title>{{ __('admin.api_tokens.ip_allowlist_heading') }}</x-ag.card.title>
                <x-ag.card.description>{{ __('admin.api_tokens.ip_allowlist_lede') }}</x-ag.card.description>
            </x-ag.card.header>
            <x-ag.card.content>
                <form class="ag-form" wire:submit="saveIpAllowlist">
                    <div class="ag-field">
                        <label class="ag-field__label" for="api-ip-allowlist">{{ __('admin.api_tokens.ip_allowlist_label') }}</label>
                        <textarea
                            id="api-ip-allowlist"
                            class="ag-input ag-input--area"
                            rows="6"
                            wire:model="apiIpAllowlist"
                            placeholder="203.0.113.10&#10;2001:db8::1"
                            spellcheck="false"
                        ></textarea>
                        <p class="ag-field__help">{{ __('admin.api_tokens.ip_allowlist_help') }}</p>
                        @error('apiIpAllowlist') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-form__actions">
                        <button class="ag-btn ag-btn--primary" type="submit" wire:loading.attr="disabled">
                            {{ __('admin.api_tokens.ip_allowlist_save') }}
                        </button>
                    </div>
                </form>
            </x-ag.card.content>
        </x-ag.card>

        <x-ag.card>
            <x-ag.card.header>
                <x-ag.card.title>{{ __('admin.api_tokens.create_heading') }}</x-ag.card.title>
                <x-ag.card.description>{{ __('admin.api_tokens.create_lede') }}</x-ag.card.description>
            </x-ag.card.header>
            <x-ag.card.content>
                <form class="ag-form" wire:submit="createToken">
                    <div class="ag-field">
                        <label class="ag-field__label" for="token-name">{{ __('admin.api_tokens.name') }}</label>
                        <input id="token-name" class="ag-input" wire:model="token_name">
                        @error('token_name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-form__actions">
                        <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.api_tokens.create') }}</button>
                    </div>
                </form>
            </x-ag.card.content>
        </x-ag.card>

        <x-ag.card>
            <x-ag.card.header>
                <x-ag.card.title>{{ __('admin.api_tokens.list_heading') }}</x-ag.card.title>
                <x-ag.card.description>{{ __('admin.api_tokens.list_lede') }}</x-ag.card.description>
            </x-ag.card.header>
            <x-ag.card.content>
                <div class="ag-table-wrap">
                    <table class="ag-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('admin.api_tokens.name') }}</th>
                                <th scope="col">{{ __('admin.api_tokens.last_used') }}</th>
                                <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tokens as $token)
                                <tr wire:key="pat-{{ $token->id }}">
                                    <td>
                                        <div class="ag-table__primary">
                                            <span class="ag-table__name">{{ $token->name }}</span>
                                            <span class="ag-muted">{{ __('admin.api_tokens.abilities') }}: {{ __('admin.api_tokens.ability_all') }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $token->last_used_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('admin.api_tokens.never') }}</td>
                                    <td class="ag-table__actions">
                                        <div class="ag-row-actions">
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
                                <tr><td colspan="3">{{ __('admin.api_tokens.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ag.card.content>
        </x-ag.card>
    </div>

    @include('livewire.admin.partials.confirm-password-modal')
</div>
