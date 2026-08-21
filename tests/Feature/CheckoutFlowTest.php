<?php

declare(strict_types=1);

use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirements;
use App\Agovena\Checkout\CheckoutFlow;
use App\Agovena\Checkout\CheckoutStep;

test('digital carts compose details, payment, then finish', function () {
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

test('mixed carts combine delivery and configuration into one fulfillment step', function () {
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
        CheckoutStep::Fulfillment,
        CheckoutStep::Payment,
        CheckoutStep::Review,
    ]);
});

test('configuration-only carts insert configure between details and payment', function () {
    $flow = new CheckoutFlow;
    $steps = $flow->stepsFor(new CartRequirements([
        CartRequirement::Billing,
        CartRequirement::ProductConfiguration,
        CartRequirement::Payment,
    ]));

    expect($steps)->toBe([
        CheckoutStep::Details,
        CheckoutStep::Configuration,
        CheckoutStep::Payment,
        CheckoutStep::Review,
    ]);
});

test('continue from payment does not advance into finish', function () {
    $flow = new CheckoutFlow;
    $requirements = new CartRequirements([
        CartRequirement::Billing,
        CartRequirement::Payment,
        CartRequirement::Review,
    ]);

    expect($flow->next($requirements, CheckoutStep::Payment))->toBeNull()
        ->and($flow->canVisit($requirements, CheckoutStep::Review, [CheckoutStep::Details->value, CheckoutStep::Payment->value]))->toBeFalse();
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
