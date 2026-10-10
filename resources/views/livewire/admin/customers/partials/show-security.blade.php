{{-- Customer workspace sidebar: security card. Part of livewire.admin.customers.show. --}}
<section class="customer-workspace__card" aria-labelledby="security-heading">
    <header class="customer-workspace__section-header">
        <x-ag.icon name="shield" :size="20" class="customer-workspace__section-icon" />
        <div>
            <p class="customer-workspace__eyebrow">{{ __('admin.customers.security_eyebrow') }}</p>
            <h2 id="security-heading" class="customer-workspace__section-title">{{ __('admin.customers.security_heading') }}</h2>
        </div>
    </header>
    @if ($user)
        <div class="customer-workspace__status-list">
            <div><span>{{ __('admin.customers.email_verification_status') }}</span><strong>{{ $user->hasVerifiedEmail() ? __('admin.customers.email_verified') : __('admin.customers.email_unverified') }}</strong></div>
            <div><span>{{ __('admin.customers.two_factor_status') }}</span><strong>{{ $user->hasTwoFactorEnabled() ? __('admin.customers.two_factor_on') : __('admin.customers.two_factor_off') }}</strong></div>
        </div>
        @can('customers.manage')
            @if ($user->hasTwoFactorEnabled())
                <div class="customer-workspace__security-warning">
                    <strong>{{ __('admin.customers.two_factor_enabled_warning_title') }}</strong>
                    <p>{{ __('admin.customers.two_factor_enabled_warning') }}</p>
                    <button class="ag-btn ag-btn--danger-outline" type="button" wire:click="disableTwoFactor" wire:confirm="{{ __('admin.customers.disable_two_factor_confirm') }}" wire:loading.attr="disabled">
                        {{ __('admin.customers.disable_two_factor') }}
                    </button>
                </div>
            @else
                <form class="customer-workspace__password-form" wire:submit="changePassword">
                    <div class="customer-workspace__subsection-header">
                        <h3>{{ __('admin.customers.password_heading') }}</h3>
                        <p>{{ __('admin.customers.password_lede') }}</p>
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="customer-password">{{ __('admin.customers.new_password') }}</label>
                        <input id="customer-password" class="ag-input" type="password" wire:model="password" autocomplete="new-password" required>
                        @error('password') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="customer-password-confirmation">{{ __('admin.customers.confirm_password') }}</label>
                        <input id="customer-password-confirmation" class="ag-input" type="password" wire:model="password_confirmation" autocomplete="new-password" required>
                    </div>
                    <button class="ag-btn ag-btn--secondary" type="submit" wire:loading.attr="disabled">{{ __('admin.customers.set_password') }}</button>
                </form>
            @endif
            <div class="customer-workspace__security-actions">
                @if ($user->hasVerifiedEmail())
                    <button class="ag-btn ag-btn--ghost" type="button" wire:click="markEmailUnverified" wire:confirm="{{ __('admin.customers.mark_email_unverified_confirm') }}">{{ __('admin.customers.mark_email_unverified') }}</button>
                @else
                    <button class="ag-btn ag-btn--ghost" type="button" wire:click="markEmailVerified" wire:confirm="{{ __('admin.customers.mark_email_verified_confirm') }}">{{ __('admin.customers.mark_email_verified') }}</button>
                @endif
            </div>
        @endcan
    @else
        <p class="customer-workspace__empty">{{ __('admin.customers.password_no_user') }}</p>
    @endif
</section>
