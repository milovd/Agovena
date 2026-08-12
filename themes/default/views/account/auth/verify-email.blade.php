<section class="store-auth">
    <h1 class="store-auth__title">{{ __('customer.auth.verify_heading') }}</h1>
    <p class="store-auth__lede">{{ __('customer.auth.verify_text') }}</p>

    <form class="store-auth__form" wire:submit="resend">
        <button class="store-btn store-btn--primary store-btn--block" type="submit">{{ __('customer.auth.resend_verification') }}</button>
    </form>

    <p class="store-auth__meta">
        <a class="store-auth__link" href="{{ route('customer.logout') }}">{{ __('customer.auth.logout') }}</a>
    </p>
</section>
