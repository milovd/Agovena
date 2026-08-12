<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

enum CustomerRegistrationMode: string
{
    case Disabled = 'disabled';
    case Optional = 'optional';
    case Required = 'required';

    public function allowsRegistration(): bool
    {
        return $this !== self::Disabled;
    }

    public function requiresAccountForCheckout(): bool
    {
        return $this === self::Required;
    }
}
