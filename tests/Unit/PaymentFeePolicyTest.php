<?php

declare(strict_types=1);

use App\Agovena\Money\Money;
use App\Agovena\Payments\PaymentFeePolicy;
use App\Agovena\Settings\SettingsRepository;

it('calculates a provider fee with integer minor-unit arithmetic', function (): void {
    app(SettingsRepository::class)->set('payments', 'gateway_fee_rules', [
        'stripe' => [
            'enabled' => true,
            'percentage_bps' => 250,
            'fixed_amount' => 30,
            'currency' => 'EUR',
        ],
    ]);

    $fee = app(PaymentFeePolicy::class)->calculate('stripe', Money::of(10_000, 'EUR'));

    expect($fee->amount)->toBe(280)
        ->and($fee->currency)->toBe('EUR')
        ->and($fee->snapshot)->toMatchArray([
            'gateway_id' => 'stripe',
            'percentage_bps' => 250,
            'fixed_amount' => 30,
            'currency' => 'EUR',
        ]);
});

it('does not apply fees to disabled or unconfigured gateways', function (): void {
    app(SettingsRepository::class)->set('payments', 'gateway_fee_rules', [
        'stripe' => ['enabled' => false, 'percentage_bps' => 250, 'fixed_amount' => 30, 'currency' => 'EUR'],
    ]);

    $policy = app(PaymentFeePolicy::class);

    expect($policy->calculate('stripe', Money::of(10_000, 'EUR'))->amount)->toBe(0)
        ->and($policy->calculate('mollie', Money::of(10_000, 'EUR'))->amount)->toBe(0);
});
