<?php

declare(strict_types=1);

namespace App\Agovena\Customer\Properties;

use App\Agovena\Customer\AddressData;
use App\Models\Customer;

final class CustomerAddressProperties
{
    /** @var array<string, string> */
    public const FIELD_MAP = [
        'phone' => 'phone',
        'company' => 'company_name',
        'country' => 'country',
        'line1' => 'address',
        'line2' => 'address2',
        'city' => 'city',
        'region' => 'state',
        'postal_code' => 'zip',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_values(self::FIELD_MAP);
    }

    public static function isAddressKey(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /** @return array<string, string|null> */
    public static function values(AddressData $address): array
    {
        return [
            'phone' => $address->phone,
            'company_name' => $address->company,
            'country' => $address->country,
            'address' => $address->line1,
            'address2' => $address->line2,
            'city' => $address->city,
            'state' => $address->region,
            'zip' => $address->postalCode,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function toAddress(Customer $customer, array $values): ?AddressData
    {
        foreach (['address', 'city', 'zip', 'country'] as $requiredKey) {
            if (trim((string) ($values[$requiredKey] ?? '')) === '') {
                return null;
            }
        }

        return AddressData::fromArray([
            'name' => $customer->name,
            'company' => $values['company_name'] ?? null,
            'line1' => $values['address'],
            'line2' => $values['address2'] ?? null,
            'city' => $values['city'],
            'region' => $values['state'] ?? null,
            'postal_code' => $values['zip'],
            'country' => $values['country'],
            'phone' => $values['phone'] ?? null,
        ]);
    }
}
