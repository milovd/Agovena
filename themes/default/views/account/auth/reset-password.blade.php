<section class="store-auth">
    <h1 class="store-auth__title">{{ __('customer.auth.reset_heading') }}</h1>

    <form class="store-auth__form" wire:submit="resetPassword">
        <div class="store-field">
            <label class="store-label" for="customer-email">{{ __('customer.auth.email') }}</label>
            <input id="customer-email" class="store-input" type="email" wire:model="email" autocomplete="username" required>
            @error('email') <p class="store-field__error">{{ $message }}</p> @enderror
        </div>

        <div class="store-field">
            <label class="store-label" for="customer-password">{{ __('customer.auth.password') }}</label>
            <input id="customer-password" class="store-input" type="password" wire:model="password" autocomplete="new-password" required>
            @error('password') <p class="store-field__error">{{ $message }}</p> @enderror
        </div>

        <div class="store-field">
            <label class="store-label" for="customer-password-confirmation">{{ __('customer.auth.password_confirmation') }}</label>
            <input id="customer-password-confirmation" class="store-input" type="password" wire:model="password_confirmation" autocomplete="new-password" required>
        </div>

        <button class="store-btn store-btn--primary" type="submit">{{ __('customer.auth.reset_password') }}</button>
    </form>
</section>
