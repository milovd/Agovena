<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Models\Order;

/**
 * Builds the post-payment Finish stepper (same chrome as live checkout).
 */
final class CheckoutFinishProgress
{
    /**
     * @return list<CheckoutProgressItem>
     */
    public function forOrder(Order $order): array
    {
        $steps = [CheckoutStep::Details];
        // Billing is often snapshotted onto shipping columns even for digital carts.
        // Only show Delivery when a real shipping quote/method was chosen.
        if ($this->hadDeliveryStep($order)) {
            $steps[] = CheckoutStep::Delivery;
        }
        $steps[] = CheckoutStep::Payment;
        $steps[] = CheckoutStep::Review;

        $total = count($steps);
        $items = [];
        foreach ($steps as $index => $step) {
            $state = $step === CheckoutStep::Review ? 'current' : 'completed';
            $items[] = new CheckoutProgressItem($step, $state, $index + 1, $total);
        }

        return $items;
    }

    private function hadDeliveryStep(Order $order): bool
    {
        return filled($order->shipping_method_label)
            || filled($order->shipping_carrier_id)
            || filled($order->shipping_service_code);
    }
}
