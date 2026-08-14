<?php

declare(strict_types=1);

use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirements;
use App\Agovena\Checkout\CheckoutFlow;
use App\Agovena\Checkout\CheckoutStep;

test('digital carts compose details payment and review', function () {
    $flow = new CheckoutFlow;
    $steps = $flow->stepsFor(new CartRequirements([
        CartRequirement::Billing,
        CartRequirement::Payment,
        CartRequirement::Review,
    ]));

    expect($steps)->toBe([
        CheckoutStep::Details,
        CheckoutStep::Payment,
        CheckoutStep::Review,
    ]);
});

test('physical carts insert delivery between details and payment', function () {
    $flow = new CheckoutFlow;
    $steps = $flow->stepsFor(new CartRequirements([
        CartRequirement::Billing,
        CartRequirement::ShippingAddress,
        CartRequirement::ShippingMethod,
        CartRequirement::Payment,
        CartRequirement::Review,
    ]));

    expect($steps)->toBe([
        CheckoutStep::Details,
        CheckoutStep::Delivery,
        CheckoutStep::Payment,
        CheckoutStep::Review,
    ]);
});

test('mixed carts include configuration without duplicating checkout implementations', function () {
    $flow = new CheckoutFlow;
    $requirements = new CartRequirements([
        CartRequirement::Billing,
        CartRequirement::ShippingAddress,
        CartRequirement::ShippingMethod,
        CartRequirement::ProductConfiguration,
        CartRequirement::CustomProperties,
        CartRequirement::Payment,
        CartRequirement::Review,
    ]);

    expect($flow->stepsFor($requirements))->toBe([
        CheckoutStep::Details,
        CheckoutStep::Delivery,
        CheckoutStep::Configuration,
        CheckoutStep::Payment,
        CheckoutStep::Review,
    ]);
});

test('customers cannot skip ahead of an incomplete checkout step', function () {
    $flow = new CheckoutFlow;
    $requirements = new CartRequirements([
        CartRequirement::Billing,
        CartRequirement::Payment,
        CartRequirement::Review,
    ]);

    expect($flow->canVisit($requirements, CheckoutStep::Payment, []))->toBeFalse()
        ->and($flow->canVisit($requirements, CheckoutStep::Details, []))->toBeTrue()
        ->and($flow->canVisit($requirements, CheckoutStep::Payment, [CheckoutStep::Details->value]))->toBeTrue();
});
