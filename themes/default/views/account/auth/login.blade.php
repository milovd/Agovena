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

        <button class="store-btn store-btn--primary" type="submit">{{ __('customer.auth.sign_in') }}</button>
    </form>

    <p class="store-auth__meta">
        <a href="{{ route('password.request') }}">{{ __('customer.auth.forgot_link') }}</a>
    </p>

    @if ($registrationEnabled)
        <p class="store-auth__meta">
            {{ __('customer.auth.no_account') }}
            <a href="{{ route('register') }}">{{ __('customer.auth.register_link') }}</a>
        </p>
    @endif
</section>
