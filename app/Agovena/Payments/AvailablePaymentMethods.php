<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\OffersCheckoutMethods;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;

/**
 * Checkout-facing discovery of enabled PaymentGateway methods.
 * Account balance is offered separately in checkout UI (not a gateway).
 * Development instant-pay is only offered when explicitly enabled and no real
 * payment extensions are registered (local/e2e). Never alongside live gateways.
 */
final class AvailablePaymentMethods
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn (array $option): string => $option['id'], $this->options());
    }

    /**
     * @return list<array{id: string, label: string, gateway_id?: string, icon?: string|null}>
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->gateways->all() as $gateway) {
            // Development pay is never listed next to real gateways.
            if ($gateway->id() === 'development') {
                continue;
            }

            if ($gateway instanceof OffersCheckoutMethods) {
                foreach ($gateway->checkoutMethods() as $method) {
                    $options[] = $method->toArray();
                }

                continue;
            }

            $options[] = [
                'id' => $gateway->id(),
                'gateway_id' => $gateway->id(),
                'label' => $gateway->label(),
                'icon' => null,
            ];
        }

        if ($options === [] && $this->developmentPayAllowed()) {
            $development = app(DevelopmentPaymentGateway::class);
            $options[] = [
                'id' => $development->id(),
                'gateway_id' => $development->id(),
                'label' => $development->label(),
                'icon' => null,
            ];
        }

        return $options;
    }

    private function developmentPayAllowed(): bool
    {
        return (bool) config('agovena.payments.allow_development_instant_pay')
            && ! app()->environment('production');
    }
}
