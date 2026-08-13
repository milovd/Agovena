<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->numberBetween(1000, 50000),
            'currency' => 'EUR',
            'method' => 'manual',
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
            'reference' => null,
        ];
    }
}
