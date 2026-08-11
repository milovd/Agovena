<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 50000);

        return [
            'number' => 'AGO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => OrderStatus::Pending,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_id' => null,
            'subtotal_amount' => $amount,
            'total_amount' => $amount,
            'currency' => 'EUR',
            'idempotency_key' => null,
        ];
    }
}
