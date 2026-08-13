<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\OffersReusablePaymentAuthorization;

final class ResolvesReusablePaymentAuthorizations
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function forCustomer(string $gatewayId, ?int $customerId, string $customerEmail): ReusablePaymentAuthorization
    {
        $gatewayId = CheckoutPaymentSelection::parse($gatewayId)->gatewayId;
        if ($gatewayId === '') {
            return ReusablePaymentAuthorization::missing('manual');
        }

        $gateway = $this->gateways->get($gatewayId);
        if ($gateway instanceof OffersReusablePaymentAuthorization) {
            return $gateway->reusableAuthorization($customerId, $customerEmail);
        }

        return ReusablePaymentAuthorization::missing($gatewayId);
    }
}
