<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel" aria-labelledby="referrals-heading">
        <header class="store-account-panel__header">
            <h1 id="referrals-heading" class="store-account-panel__title">{{ __('customer.referrals.title') }}</h1>
            <p class="store-account-panel__lede">{{ __('customer.referrals.lede') }}</p>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        <form wire:submit="createCode" class="store-auth__form" aria-labelledby="referral-code-heading">
            <h2 id="referral-code-heading" class="store-account-panel__subtitle">{{ __('customer.referrals.create_heading') }}</h2>
            <div class="store-field">
                <label class="store-label" for="referral-code">{{ __('customer.referrals.code') }}</label>
                <input id="referral-code" class="store-input" wire:model="newCode" type="text" autocomplete="off" required>
                @error('newCode') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-form-actions">
                <button class="store-btn store-btn--primary" type="submit">{{ __('customer.referrals.create') }}</button>
            </div>
        </form>

        <h2 class="store-account-panel__subtitle">{{ __('customer.referrals.codes_heading') }}</h2>
        <ul class="store-list">
            @forelse ($codes as $code)
                <li class="store-list__item"><strong>{{ $code->code }}</strong> <span>{{ $code->uses_count }} {{ __('customer.referrals.uses') }}</span></li>
            @empty
                <li class="store-list__item">{{ __('customer.referrals.empty') }}</li>
            @endforelse
        </ul>
    </section>
</div>
