<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('admin.api_tokens.title') }}</h1>
            <p class="store-account-panel__lede">{{ __('admin.api_tokens.lede') }}</p>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @if ($plainTextToken)
            <p class="store-alert store-alert--warning" role="status">{{ __('admin.api_tokens.copy_once') }}</p>
            <p class="store-ticket__code">{{ $plainTextToken }}</p>
        @endif

        <form class="store-auth__form" wire:submit="createToken">
            <div class="store-field">
                <label class="store-label" for="api-token-name">{{ __('admin.api_tokens.name') }}</label>
                <input id="api-token-name" class="store-input" type="text" wire:model="token_name">
                @error('token_name') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <button class="store-btn store-btn--primary" type="submit">{{ __('admin.api_tokens.create') }}</button>
        </form>

        @if ($tokens->isEmpty())
            <p class="store-muted">{{ __('admin.api_tokens.empty') }}</p>
        @else
            <ul class="store-order-items" role="list">
                @foreach ($tokens as $token)
                    <li class="store-order-items__row" wire:key="cust-pat-{{ $token->id }}">
                        <div>
                            <strong>{{ $token->name }}</strong>
                            <p>{{ __('admin.api_tokens.last_used') }}: {{ $token->last_used_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? __('admin.api_tokens.never') }}</p>
                        </div>
                        <button
                            type="button"
                            class="store-btn store-btn--secondary"
                            wire:click="revokeToken({{ $token->id }})"
                            wire:confirm="{{ __('admin.api_tokens.revoke_confirm') }}"
                        >{{ __('admin.api_tokens.revoke') }}</button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
