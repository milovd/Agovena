<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

enum ExtensionCategory: string
{
    case PaymentGateway = 'payment_gateway';
    case Provisioning = 'provisioning';
    case Shipping = 'shipping';
    case Authentication = 'authentication';
    case Storage = 'storage';
    case Notifications = 'notifications';
    case Analytics = 'analytics';
    case Tax = 'tax';
    case Other = 'other';

    public function labelKey(): string
    {
        return 'admin.extensions.categories.'.$this->value;
    }
}
