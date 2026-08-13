<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\SynchronizesPayments;
use App\Models\Payment;

/**
 * Asks a gateway to fetch authoritative provider status. Return URLs are UX only.
 */
final class ReconcilePaymentStatus
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
    ) {}

    public function handle(Payment $payment): Payment
    {
        $gateway = $this->gateways->get($payment->method);
        if (! $gateway instanceof SynchronizesPayments) {
            return $payment;
        }

        return $gateway->syncStatus($payment);
    }
}
