<?php

declare(strict_types=1);

namespace App\Agovena\Customer\Properties;

final class ReservedCustomerPropertyKeys
{
    /** @var list<string> */
    public const KEYS = [
        'email',
        'password',
        'password_confirmation',
        'name',
        'user_id',
        'billing',
        'billing_name',
        'billing_company',
        'billing_line1',
        'billing_line2',
        'billing_city',
        'billing_region',
        'billing_postal_code',
        'billing_country',
        'billing_phone',
        'shipping',
        'shipping_name',
        'shipping_company',
        'shipping_line1',
        'shipping_line2',
        'shipping_city',
        'shipping_region',
        'shipping_postal_code',
        'shipping_country',
        'shipping_phone',
        'tax_country',
        'shipping_country_code',
    ];

    public static function contains(string $key): bool
    {
        return in_array(strtolower($key), self::KEYS, true);
    }
}
