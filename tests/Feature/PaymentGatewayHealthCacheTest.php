<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\HealthResult;
use App\Agovena\Payments\PaymentGatewayCapabilities;
use App\Agovena\Payments\PaymentGatewayRegistry;

function healthCountingGateway(bool &$healthy, int &$calls): PaymentGateway
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('id')->andReturn('counting-gateway');
    $gateway->shouldReceive('label')->andReturn('Counting gateway');
    $gateway->shouldReceive('capabilities')->andReturn(new PaymentGatewayCapabilities);
    $gateway->shouldReceive('health')->andReturnUsing(function () use (&$healthy, &$calls): HealthResult {
        $calls++;

        return $healthy ? HealthResult::ok() : HealthResult::fail('Rejected');
    });

    return $gateway;
}

test('checkout reuses gateway health instead of calling the provider on every lookup', function () {
    $healthy = true;
    $calls = 0;
    app(PaymentGatewayRegistry::class)->register(healthCountingGateway($healthy, $calls));

    $methods = app(AvailablePaymentMethods::class);
    $methods->ids();
    $methods->options('NL');
    app(AvailablePaymentMethods::class)->ids();

    expect($methods->ids())->toContain('counting-gateway')
        ->and($calls)->toBe(1);
});

test('changing gateway settings refreshes the cached health result', function () {
    $healthy = false;
    $calls = 0;
    app(PaymentGatewayRegistry::class)->register(healthCountingGateway($healthy, $calls));

    expect(app(AvailablePaymentMethods::class)->ids())->not->toContain('counting-gateway');

    $healthy = true;
    app()->forgetScopedInstances();
    expect(app(AvailablePaymentMethods::class)->ids())->not->toContain('counting-gateway');

    $this->travel(1)->seconds();
    app(ExtensionSettingsRepository::class)->set('counting-gateway', 'api_key', 'replaced', secret: true);
    app()->forgetScopedInstances();

    expect(app(AvailablePaymentMethods::class)->ids())->toContain('counting-gateway')
        ->and($calls)->toBe(2);
});
