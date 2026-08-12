<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\Customer;
use App\Models\Order;

final class AttachGuestOrdersToCustomer
{
    /**
     * Link historical guest orders that already used this verified email.
     */
    public function handle(Customer $customer): int
    {
        if ($customer->email_verified_at === null) {
            return 0;
        }

        return Order::query()
            ->whereNull('customer_id')
            ->where('customer_email', $customer->email)
            ->update(['customer_id' => $customer->id]);
    }
}
