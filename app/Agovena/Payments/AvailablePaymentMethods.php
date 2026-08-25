<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\OffersCheckoutMethods;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;

/**
 * Checkout-facing discovery of enabled PaymentGateway methods.
 * Account balance is not a gateway option — it reduces the amount due at place-order.
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
        $gateways = $this->gateways->all();
        if ($gateways === []) {
            return $this->coreFallbackOptions();
        }

        $options = [];
        foreach ($gateways as $gateway) {
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

        return $options;
    }

    /**
     * @return list<array{id: string, label: string, gateway_id: string, icon: null}>
     */
    private function coreFallbackOptions(): array
    {
        if (! (bool) config('agovena.payments.allow_development_instant_pay')) {
            return [];
        }

        if (app()->environment('production')) {
            return [];
        }

        return [[
            'id' => 'development',
            'gateway_id' => 'development',
            'label' => app(DevelopmentPaymentGateway::class)->label(),
            'icon' => null,
        ]];
    }
}
