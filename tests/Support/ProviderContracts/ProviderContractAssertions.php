<?php

declare(strict_types=1);

namespace Tests\Support\ProviderContracts;

use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\HealthResult;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Shipping\CarrierShipmentResult;
use App\Agovena\Shipping\Contracts\ShippingCarrier;
use App\Agovena\Shipping\ShippingRateQuote;

final class ProviderContractAssertions
{
    public static function assertPaymentGateway(PaymentGateway $gateway): void
    {
        expect($gateway->id())->not->toBe('')
            ->and($gateway->label())->not->toBe('')
            ->and($gateway->capabilities())->not->toBeNull();

        $health = $gateway->health();
        expect($health)->toBeInstanceOf(HealthResult::class);
        self::assertNoSecretLeak($health->message);
        self::assertNoSecretLeak($gateway->id());
        self::assertNoSecretLeak($gateway->label());
    }

    public static function assertProvisioner(Provisioner $provisioner): void
    {
        expect($provisioner->id())->not->toBe('')
            ->and($provisioner->label())->not->toBe('');
        self::assertNoSecretLeak($provisioner->id());
        self::assertNoSecretLeak($provisioner->label());
    }

    public static function assertShippingCarrier(ShippingCarrier $carrier): void
    {
        expect($carrier->id())->not->toBe('')
            ->and($carrier->label())->not->toBe('');
        self::assertNoSecretLeak($carrier->id());
        self::assertNoSecretLeak($carrier->label());
    }

    public static function assertRateQuote(ShippingRateQuote $quote): void
    {
        expect($quote->carrierId)->not->toBe('')
            ->and($quote->serviceCode)->not->toBe('')
            ->and($quote->serviceLabel)->not->toBe('')
            ->and($quote->amount)->toBeGreaterThanOrEqual(0)
            ->and($quote->currency)->not->toBe('');
        self::assertNoSecretLeak($quote->serviceLabel);
    }

    public static function assertShipmentResult(CarrierShipmentResult $result): void
    {
        expect($result->externalId)->not->toBe('')
            ->and($result->status)->not->toBe('');
        self::assertNoSecretLeak($result->externalId);
        if ($result->trackingNumber !== null) {
            self::assertNoSecretLeak($result->trackingNumber);
        }
    }

    public static function assertNoSecretLeak(string $value): void
    {
        expect($value)
            ->not->toMatch('/\b(sk|pk|rk|whsec)_(live|test)?[_A-Za-z0-9]{8,}/i')
            ->not->toMatch('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i');
    }
}
