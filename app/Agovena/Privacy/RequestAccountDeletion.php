<?php

declare(strict_types=1);

namespace App\Agovena\Privacy;

use App\Models\Customer;

final class RequestAccountDeletion
{
    public function handle(Customer $customer): void
    {
        if ($customer->deletion_requested_at === null) {
            $customer->forceFill(['deletion_requested_at' => now()])->save();
        }
    }
}
