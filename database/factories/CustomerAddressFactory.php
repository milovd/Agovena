<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => 'Home',
            'name' => fake()->name(),
            'company' => null,
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'city' => fake()->city(),
            'region' => null,
            'postal_code' => fake()->postcode(),
            'country' => 'NL',
            'phone' => null,
            'is_default_billing' => false,
            'is_default_shipping' => false,
        ];
    }
}
