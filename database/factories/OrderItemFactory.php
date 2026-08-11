<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unit = fake()->numberBetween(100, 5000);
        $qty = fake()->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'label' => fake()->words(3, true),
            'quantity' => $qty,
            'unit_amount' => $unit,
            'line_total_amount' => $unit * $qty,
            'currency' => 'EUR',
        ];
    }
}
