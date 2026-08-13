<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
final class AccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Customer $customer */
        $customer = $this->resource;
        $customer->loadMissing('user');

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'email_verified' => $customer->hasVerifiedEmail(),
        ];
    }
}
