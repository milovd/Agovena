<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\OffersCheckoutMethods;

/**
 * Checkout-facing discovery of enabled PaymentGateway methods.
 * Account balance is offered separately in checkout UI (not a gateway).
 * Development instant-pay is never auto-injected - register it explicitly when needed.
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
            // Development pay is test-only; never surface it as a storefront choice.
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

        return $options;
    }
}
