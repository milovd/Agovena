@php
    use App\Agovena\Checkout\CheckoutStep;
    $due = $amountDue ?? $orderTotal ?? $subtotal;
    $currentPosition = collect($progressItems)->first(fn ($item) => $item->isCurrent())?->position ?? 1;
    $stepTotal = $progressItems[0]->total ?? count($progressItems);
    $progressPercent = (int) round((($currentPosition - 1) / max(1, $stepTotal)) * 100);
    $selectedPaymentLabel = collect($paymentOptions)->firstWhere('id', $payment_method)['label'] ?? null;
    $deliveryProgress = collect($progressItems)->first(fn ($item) => $item->step->includesDelivery());
@endphp

<div class="store-checkout">
    <header class="store-checkout__intro">
        <h1 class="store-title">{{ __('storefront.checkout.title') }}</h1>
        @if (! $customerLoggedIn && $registrationEnabled)
            <p class="store-note">
                {{ __('customer.checkout.sign_in_prompt') }}
                <a href="{{ route('login') }}">{{ __('customer.checkout.sign_in_link') }}</a>
            </p>
        @endif
    </header>

    @include('theme::checkout.partials.stepper', [
        'progressItems' => $progressItems,
        'currentLabelKey' => $currentStep->labelKey(),
        'progressPercent' => $progressPercent,
        'interactive' => true,
    ])

    <div class="store-checkout__layout">
        <div class="store-checkout__main">
            @if ($currentStep === CheckoutStep::Details)
                @include('theme::checkout.partials.details')
            @endif

            @if ($currentStep->includesDelivery())
                @include('theme::checkout.partials.delivery')
            @endif

            @if ($currentStep->includesConfiguration())
                @include('theme::checkout.partials.configuration')
            @endif

            @if ($currentStep === CheckoutStep::Payment)
                @include('theme::checkout.partials.payment')
            @endif

            <div class="store-checkout__actions">
                @if ($currentStep !== CheckoutStep::Details)
                    <button type="button" class="store-btn store-btn--ghost" wire:click="back">{{ __('storefront.checkout.back') }}</button>
                @endif
                @if ($currentStep === CheckoutStep::Payment)
                    <button type="button" class="store-btn store-btn--primary store-btn--checkout" data-testid="checkout-submit" wire:click="placeOrder" wire:loading.attr="disabled" wire:target="placeOrder">
                        <span wire:loading.remove wire:target="placeOrder">{{ $primaryActionLabel }}</span>
                        <span wire:loading wire:target="placeOrder">{{ __('storefront.checkout.working') }}</span>
                    </button>
                @else
                    <button type="button" class="store-btn store-btn--primary store-btn--checkout" data-testid="checkout-continue" wire:click="continueStep" wire:loading.attr="disabled" wire:target="continueStep">
                        <span wire:loading.remove wire:target="continueStep">{{ $primaryActionLabel }}</span>
                        <span wire:loading wire:target="continueStep">{{ __('storefront.checkout.working') }}</span>
                    </button>
                @endif
            </div>
        </div>

        @include('theme::checkout.partials.summary')
    </div>
</div>
