<?php

declare(strict_types=1);

namespace App\Agovena\Privacy;

use App\Models\Customer;

final class RequestAccountDeletion
{
    public function handle(Customer $customer): void
    {
        $user = $customer->user;
        if ($user !== null && $user->deletion_requested_at === null) {
            $user->forceFill(['deletion_requested_at' => now()])->save();
        }
    }
}
