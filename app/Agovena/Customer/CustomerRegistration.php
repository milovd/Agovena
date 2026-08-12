<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Agovena\Settings\SettingsRepository;

final class CustomerRegistration
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function mode(): CustomerRegistrationMode
    {
        $raw = (string) $this->settings->get('store', 'customer_registration', CustomerRegistrationMode::Optional->value);

        return CustomerRegistrationMode::tryFrom($raw) ?? CustomerRegistrationMode::Optional;
    }

    public function allowsRegistration(): bool
    {
        return $this->mode()->allowsRegistration();
    }

    public function requiresAccountForCheckout(): bool
    {
        return $this->mode()->requiresAccountForCheckout();
    }
}
