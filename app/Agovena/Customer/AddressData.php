<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

final readonly class AddressData
{
    public function __construct(
        public string $name,
        public string $line1,
        public string $city,
        public string $postalCode,
        public string $country,
        public ?string $company = null,
        public ?string $line2 = null,
        public ?string $region = null,
        public ?string $phone = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            name: (string) $input['name'],
            line1: (string) $input['line1'],
            city: (string) $input['city'],
            postalCode: (string) $input['postal_code'],
            country: strtoupper((string) $input['country']),
            company: self::nullableString($input['company'] ?? null),
            line2: self::nullableString($input['line2'] ?? null),
            region: self::nullableString($input['region'] ?? null),
            phone: self::nullableString($input['phone'] ?? null),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toOrderBillingColumns(): array
    {
        return [
            'billing_name' => $this->name,
            'billing_company' => $this->company,
            'billing_line1' => $this->line1,
            'billing_line2' => $this->line2,
            'billing_city' => $this->city,
            'billing_region' => $this->region,
            'billing_postal_code' => $this->postalCode,
            'billing_country' => $this->country,
            'billing_phone' => $this->phone,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function toOrderShippingColumns(): array
    {
        return [
            'shipping_name' => $this->name,
            'shipping_company' => $this->company,
            'shipping_line1' => $this->line1,
            'shipping_line2' => $this->line2,
            'shipping_city' => $this->city,
            'shipping_region' => $this->region,
            'shipping_postal_code' => $this->postalCode,
            'shipping_country' => $this->country,
            'shipping_phone' => $this->phone,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function toCustomerAddressAttributes(): array
    {
        return [
            'name' => $this->name,
            'company' => $this->company,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'city' => $this->city,
            'region' => $this->region,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
            'phone' => $this->phone,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
