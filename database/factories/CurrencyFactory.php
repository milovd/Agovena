<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->currencyCode().' currency',
            'prefix' => '',
            'suffix' => '',
            'precision' => 2,
            'exchange_rate' => '1.00000000',
            'is_active' => true,
        ];
    }
}
