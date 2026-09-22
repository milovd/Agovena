<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\OffersCheckoutMethods;

/**
 * Checkout-facing discovery of enabled PaymentGateway methods.
 * Account balance is offered separately in checkout UI (not a gateway).
 */
final class AvailablePaymentMethods
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    /**
     * @return list<string>
     */
    public function ids(?string $country = null): array
    {
        return array_map(static fn (array $option): string => $option['id'], $this->options($country));
    }

    /**
     * @return list<array{id: string, label: string, gateway_id?: string, icon?: string|null, metadata?: array<string, mixed>}>
     */
    public function options(?string $country = null): array
    {
        $options = [];
        foreach ($this->gateways->all() as $gateway) {
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

        return array_values(array_filter(
            $options,
            fn (array $option): bool => $this->isAvailableInCountry($option, $country),
        ));
    }

    /**
     * @param  array<string, mixed>  $option
     */
    private function isAvailableInCountry(array $option, ?string $country): bool
    {
        $country = strtoupper(trim((string) $country));
        if ($country === '') {
            return true;
        }

        $metadata = $option['metadata'] ?? [];
        $supportedCountries = is_array($metadata) ? ($metadata['customer_countries'] ?? []) : [];
        if (! is_array($supportedCountries) || $supportedCountries === []) {
            return true;
        }

        return in_array($country, array_map('strtoupper', array_map('strval', $supportedCountries)), true);
    }
}
