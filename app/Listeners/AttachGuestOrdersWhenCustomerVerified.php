<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Agovena\Customer\AttachGuestOrdersToCustomer;
use App\Models\Customer;
use Illuminate\Auth\Events\Verified;

final class AttachGuestOrdersWhenCustomerVerified
{
    public function __construct(
        private readonly AttachGuestOrdersToCustomer $attachGuestOrders,
    ) {}

    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof Customer) {
            return;
        }

        $this->attachGuestOrders->handle($user);
    }
}
