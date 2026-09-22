<?php

declare(strict_types=1);

test('payment method icons resolve local and provider icon sources consistently', function (): void {
    $card = view('components.ag.payment-method-icon', [
        'icon' => 'ag:payment-method/card',
        'methodId' => 'stripe:card',
        'size' => 36,
    ])->render();

    $twint = view('components.ag.payment-method-icon', [
        'icon' => null,
        'methodId' => 'stripe:twint',
        'size' => 36,
    ])->render();

    $remote = view('components.ag.payment-method-icon', [
        'icon' => 'https://stripe.com/example.svg',
        'methodId' => 'stripe:card',
        'size' => 36,
    ])->render();

    expect($card)
        ->toContain('/images/payment-methods/card.svg')
        ->not->toContain('ag:payment-method/card')
        ->and($twint)->toContain('/images/payment-methods/twint.svg')
        ->and($remote)->toContain('src="https://stripe.com/example.svg"');
});
