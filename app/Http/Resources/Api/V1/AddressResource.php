<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Agovena\Customer\AddressData;
use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress|AddressData
 */
final class AddressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CustomerAddress|AddressData $address */
        $address = $this->resource;
        if ($address instanceof AddressData) {
            return [
                'id' => null,
                'label' => __('customer.addresses.checkout_saved_label'),
                'name' => $address->name,
                'company' => $address->company,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'region' => $address->region,
                'postal_code' => $address->postalCode,
                'country' => $address->country,
                'phone' => $address->phone,
                'is_default_billing' => true,
                'is_default_shipping' => false,
                'properties' => [
                    'phone' => $address->phone,
                    'company_name' => $address->company,
                    'country' => $address->country,
                    'address' => $address->line1,
                    'address2' => $address->line2,
                    'city' => $address->city,
                    'state' => $address->region,
                    'zip' => $address->postalCode,
                ],
            ];
        }

        return [
            'id' => $address->id,
            'label' => $address->label,
            'name' => $address->name,
            'company' => $address->company,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'region' => $address->region,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'phone' => $address->phone,
            'is_default_billing' => $address->is_default_billing,
            'is_default_shipping' => $address->is_default_shipping,
            'properties' => [
                'phone' => $address->phone,
                'company_name' => $address->company,
                'country' => $address->country,
                'address' => $address->line1,
                'address2' => $address->line2,
                'city' => $address->city,
                'state' => $address->region,
                'zip' => $address->postal_code,
            ],
        ];
    }
}
