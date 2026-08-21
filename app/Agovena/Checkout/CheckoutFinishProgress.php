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
        if (filled($order->shipping_line1) || filled($order->shipping_method_label)) {
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
}
