<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Agovena\Cart\CartService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlaceOrder
{
    public function __construct(private readonly CartService $cart) {}

    /**
     * @param  array{customer_name: string, customer_email: string, idempotency_key?: string|null}  $guest
     */
    public function handle(array $guest): Order
    {
        $idempotencyKey = $guest['idempotency_key'] ?? null;

        if (filled($idempotencyKey)) {
            $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $existing->load(['items', 'payment']);
            }
        }

        if ($this->cart->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $lines = $this->cart->pricedLines();
        $subtotal = $this->cart->subtotal();

        if ($subtotal === null) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $order = DB::transaction(function () use ($guest, $lines, $subtotal, $idempotencyKey): Order {
            $order = Order::query()->create([
                'number' => $this->generateNumber(),
                'status' => OrderStatus::Pending,
                'customer_name' => $guest['customer_name'],
                'customer_email' => $guest['customer_email'],
                'customer_id' => null,
                'subtotal_amount' => $subtotal->amount,
                'total_amount' => $subtotal->amount,
                'currency' => $subtotal->currency,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($lines as $line) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line->productId,
                    'label' => $line->label,
                    'quantity' => $line->quantity,
                    'unit_amount' => $line->unitPrice->amount,
                    'line_total_amount' => $line->lineTotal->amount,
                    'currency' => $line->unitPrice->currency,
                ]);
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'amount' => $subtotal->amount,
                'currency' => $subtotal->currency,
                'method' => PaymentMethod::Manual,
                'status' => PaymentStatus::Pending,
                'paid_at' => null,
                'reference' => null,
            ]);

            return $order->load(['items', 'payment']);
        });

        $this->cart->clear();

        event(new OrderCreated($order));

        return $order;
    }

    private function generateNumber(): string
    {
        do {
            $number = 'AGO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
