<section class="store-auth">
    <h1 class="store-auth__title">{{ __('customer.auth.two_factor.heading') }}</h1>
    <p class="store-auth__lede">{{ __('customer.auth.two_factor.lede') }}</p>

    <form class="store-auth__form" wire:submit="authenticate">
        @if ($recovery)
            <div class="store-field">
                <label class="store-label" for="two-factor-recovery">{{ __('customer.auth.two_factor.recovery_code') }}</label>
                <input
                    id="two-factor-recovery"
                    class="store-input"
                    type="text"
                    wire:model="recovery_code"
                    autocomplete="one-time-code"
                    required
                >
                @error('recovery_code') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
        @else
            <div class="store-field">
                <label class="store-label" for="two-factor-code">{{ __('customer.auth.two_factor.code') }}</label>
                <input
                    id="two-factor-code"
                    class="store-input"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    wire:model="code"
                    autocomplete="one-time-code"
                    required
                >
                @error('code') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
        @endif

        <button class="store-btn store-btn--primary store-btn--block" type="submit">
            {{ __('customer.auth.two_factor.verify') }}
        </button>
    </form>

    <p class="store-auth__meta">
        @if ($recovery)
            <button type="button" class="store-auth__link" wire:click="useCode">{{ __('customer.auth.two_factor.use_code') }}</button>
        @else
            <button type="button" class="store-auth__link" wire:click="useRecovery">{{ __('customer.auth.two_factor.use_recovery') }}</button>
        @endif
    </p>
</section>
