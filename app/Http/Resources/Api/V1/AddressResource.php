<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
final class AddressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CustomerAddress $address */
        $address = $this->resource;

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
        ];
    }
}
