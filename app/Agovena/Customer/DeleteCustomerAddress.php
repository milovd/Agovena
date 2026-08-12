<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\Customer;
use App\Models\CustomerAddress;

final class DeleteCustomerAddress
{
    public function handle(Customer $customer, CustomerAddress $address): void
    {
        abort_unless((int) $address->customer_id === (int) $customer->id, 404);

        $address->delete();
    }
}
