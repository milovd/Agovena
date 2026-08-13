<section class="store-auth">
    <h1 class="store-auth__title">{{ __('customer.auth.register_heading') }}</h1>

    <form class="store-auth__form" wire:submit="register">
        <div class="store-field">
            <label class="store-label" for="customer-name">{{ __('customer.auth.name') }}</label>
            <input id="customer-name" class="store-input" type="text" wire:model="name" autocomplete="name" required>
            @error('name') <p class="store-field__error">{{ $message }}</p> @enderror
        </div>

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

        @include('partials.custom-property-fields', ['actor' => 'customer'])

        <div class="store-field">
            <label class="store-label" for="customer-password-confirmation">{{ __('customer.auth.password_confirmation') }}</label>
            <input id="customer-password-confirmation" class="store-input" type="password" wire:model="password_confirmation" autocomplete="new-password" required>
        </div>

        <button class="store-btn store-btn--primary store-btn--block" type="submit">{{ __('customer.auth.create_account') }}</button>
    </form>

    <p class="store-auth__meta">
        {{ __('customer.auth.have_account') }}
        <a class="store-auth__link" href="{{ route('login') }}">{{ __('customer.auth.login_link') }}</a>
    </p>
</section>
