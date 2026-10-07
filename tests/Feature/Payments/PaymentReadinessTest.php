<?php

declare(strict_types=1);

use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\HealthResult;
use App\Agovena\Payments\PaymentGatewayRegistry;

it('does not expose an unhealthy payment gateway and uses the local demo fallback', function (): void {
    config(['agovena.payments.allow_development_instant_pay' => true]);
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('health')->andReturn(HealthResult::fail('not configured'));
    $gateway->shouldReceive('id')->andReturn('fixture-unhealthy');

    $registry = app(PaymentGatewayRegistry::class);
    $registry->clear();
    $registry->register($gateway);

    expect(app(AvailablePaymentMethods::class)->ids())->toBe(['development']);
});
