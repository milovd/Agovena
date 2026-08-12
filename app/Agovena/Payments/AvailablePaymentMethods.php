<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

/**
 * Checkout-facing discovery of enabled PaymentGateway ids.
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
        $ids = $this->gateways->ids();
        if ($ids !== []) {
            return $ids;
        }

        // Core fallback when no payment Extensions are enabled yet.
        $fallback = ['manual'];
        if ((bool) config('agovena.payments.allow_development_instant_pay')) {
            $fallback[] = 'development';
        }

        return $fallback;
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->ids() as $id) {
            $gateway = $this->gateways->get($id);
            $label = $gateway?->label() ?? match ($id) {
                'development' => 'storefront.checkout.payment_development',
                default => 'storefront.checkout.payment_manual',
            };
            $options[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        return $options;
    }
}
