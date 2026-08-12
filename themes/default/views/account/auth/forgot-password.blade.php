<section class="store-auth">
    <h1 class="store-auth__title">{{ __('customer.auth.forgot_heading') }}</h1>

    @if ($status)
        <p class="store-flash" role="status">{{ $status }}</p>
    @endif

    <form class="store-auth__form" wire:submit="sendResetLink">
        <div class="store-field">
            <label class="store-label" for="customer-email">{{ __('customer.auth.email') }}</label>
            <input id="customer-email" class="store-input" type="email" wire:model="email" autocomplete="username" required>
            @error('email') <p class="store-field__error">{{ $message }}</p> @enderror
        </div>

        <button class="store-btn store-btn--primary" type="submit">{{ __('customer.auth.send_reset_link') }}</button>
    </form>

    <p class="store-auth__meta">
        <a href="{{ route('login') }}">{{ __('customer.auth.login_link') }}</a>
    </p>
</section>
