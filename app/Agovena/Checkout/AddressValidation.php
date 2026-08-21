<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use Illuminate\Validation\Rule;

final class AddressValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(string $prefix): array
    {
        return [
            "{$prefix}_name" => ['required', 'string', 'max:255'],
            "{$prefix}_company" => ['nullable', 'string', 'max:255'],
            "{$prefix}_line1" => ['required', 'string', 'max:255'],
            "{$prefix}_line2" => ['nullable', 'string', 'max:255'],
            "{$prefix}_city" => ['required', 'string', 'max:255'],
            "{$prefix}_region" => ['nullable', 'string', 'max:255'],
            "{$prefix}_postal_code" => ['required', 'string', 'max:32'],
            "{$prefix}_country" => ['required', 'string', 'size:2', Rule::in(CheckoutCountries::codes())],
            "{$prefix}_phone" => ['nullable', 'string', 'max:64'],
        ];
    }
}
