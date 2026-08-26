<section class="store-auth">
    <h1 class="store-auth__title">{{ __('customer.auth.login_heading') }}</h1>

    <form class="store-auth__form" wire:submit="login">
        <div class="store-field">
            <label class="store-label" for="customer-email">{{ __('customer.auth.email') }}</label>
            <input id="customer-email" class="store-input" type="email" wire:model="email" autocomplete="username" required>
            @error('email') <p class="store-field__error">{{ $message }}</p> @enderror
        </div>

        <div class="store-field">
            <label class="store-label" for="customer-password">{{ __('customer.auth.password') }}</label>
            <input id="customer-password" class="store-input" type="password" wire:model="password" autocomplete="current-password" required>
            @error('password') <p class="store-field__error">{{ $message }}</p> @enderror
        </div>

        <label class="store-check">
            <input type="checkbox" wire:model="remember">
            <span>{{ __('customer.auth.remember') }}</span>
        </label>

        <button class="store-btn store-btn--primary store-btn--block" type="submit">{{ __('customer.auth.sign_in') }}</button>
    </form>

    @if ($oauthProviders !== [])
        <div class="store-auth__oauth" aria-label="{{ __('customer.auth.oauth_heading') }}">
            <p class="store-auth__oauth-heading">{{ __('customer.auth.oauth_heading') }}</p>
            @foreach ($oauthProviders as $oauthProvider)
                <a class="store-btn store-btn--outline store-btn--block" href="{{ route('oauth.redirect', ['provider' => $oauthProvider->id]) }}">
                    {{ __('customer.auth.continue_with', ['provider' => ucfirst($oauthProvider->id)]) }}
                </a>
            @endforeach
        </div>
    @endif

    <p class="store-auth__meta">
        <a class="store-auth__link" href="{{ route('password.request') }}">{{ __('customer.auth.forgot_link') }}</a>
    </p>

    @if ($registrationEnabled)
        <p class="store-auth__meta">
            {{ __('customer.auth.no_account') }}
            <a class="store-auth__link" href="{{ route('register') }}">{{ __('customer.auth.register_link') }}</a>
        </p>
    @endif
</section>
