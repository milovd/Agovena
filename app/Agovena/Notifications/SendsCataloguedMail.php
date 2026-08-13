<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\Customer;
use App\Notifications\CataloguedMailNotification;
use Illuminate\Support\Facades\Notification;

final class SendsCataloguedMail
{
    /**
     * @param  array<string, scalar|null>  $vars
     */
    public function toOrderCustomer(?int $customerId, string $email, string $key, array $vars): void
    {
        if ($email === '') {
            return;
        }

        $notification = new CataloguedMailNotification($key, $vars);
        $customer = $customerId !== null ? Customer::query()->find($customerId) : null;
        if ($customer instanceof Customer) {
            $customer->notify($notification);

            return;
        }

        Notification::route('mail', $email)->notify($notification);
    }
}
