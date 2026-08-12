<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Agovena\Cart\CartService;
use App\Agovena\Customer\AddressData;
use App\Agovena\Payments\CompleteDevelopmentPayment;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Events\OrderPlacing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlaceOrder
{
    public function __construct(
        private readonly CartService $cart,
        private readonly SettingsRepository $settings,
        private readonly CompleteDevelopmentPayment $developmentPayment,
    ) {}

    /**
     * @param  array{
     *     customer_name: string,
     *     customer_email: string,
     *     idempotency_key?: string|null,
     *     payment_method?: string|null,
     *     customer_id?: int|null,
     *     billing?: AddressData|null,
     *     shipping?: AddressData|null,
     *     shipping_same_as_billing?: bool
     * }  $guest
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
                'cart' => __('storefront.errors.cart_empty'),
            ]);
        }

        $lines = $this->cart->pricedLines();
        $subtotal = $this->cart->subtotal();

        if ($subtotal === null) {
            throw ValidationException::withMessages([
                'cart' => __('storefront.errors.cart_empty'),
            ]);
        }

        $method = $this->resolvePaymentMethod($guest['payment_method'] ?? PaymentMethod::Manual->value);
        $customerId = isset($guest['customer_id']) ? (int) $guest['customer_id'] : null;
        if ($customerId !== null && $customerId < 1) {
            $customerId = null;
        }

        event(new OrderPlacing($lines));

        /** @var AddressData|null $billing */
        $billing = $guest['billing'] ?? null;
        $shippingSame = (bool) ($guest['shipping_same_as_billing'] ?? true);
        /** @var AddressData|null $shipping */
        $shipping = $shippingSame ? $billing : ($guest['shipping'] ?? null);

        $order = DB::transaction(function () use ($guest, $lines, $subtotal, $idempotencyKey, $method, $customerId, $billing, $shipping, $shippingSame): Order {
            $payload = [
                'number' => $this->generateNumber(),
                'status' => OrderStatus::Pending,
                'customer_name' => $guest['customer_name'],
                'customer_email' => $guest['customer_email'],
                'customer_id' => $customerId,
                'subtotal_amount' => $subtotal->amount,
                'total_amount' => $subtotal->amount,
                'currency' => $subtotal->currency,
                'idempotency_key' => $idempotencyKey,
                'shipping_same_as_billing' => $shippingSame,
            ];

            if ($billing instanceof AddressData) {
                $payload = [...$payload, ...$billing->toOrderBillingColumns()];
            }

            if ($shipping instanceof AddressData) {
                $payload = [...$payload, ...$shipping->toOrderShippingColumns()];
            }

            $order = Order::query()->create($payload);

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
                'method' => $method,
                'status' => PaymentStatus::Pending,
                'paid_at' => null,
                'reference' => null,
            ]);

            $order = $order->load(['items', 'payment']);

            // Inside the transaction so Module listeners (e.g. inventory) can roll back on failure.
            event(new OrderCreated($order));

            return $order;
        });

        $this->cart->clear();

        if ($method === PaymentMethod::Development) {
            $this->developmentPayment->handle($order);

            return $order->fresh(['items', 'payment']) ?? $order;
        }

        return $order;
    }

    private function resolvePaymentMethod(string $value): PaymentMethod
    {
        $method = PaymentMethod::tryFrom($value) ?? PaymentMethod::Manual;

        if ($method === PaymentMethod::Development) {
            $allowed = (bool) config('agovena.payments.allow_development_instant_pay');
            if (! $allowed || app()->environment('production')) {
                throw ValidationException::withMessages([
                    'payment_method' => __('storefront.errors.development_payment_unavailable'),
                ]);
            }
        }

        return $method;
    }

    private function generateNumber(): string
    {
        $prefix = (string) $this->settings->get('store', 'order_number_prefix', 'AGO');
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix) ?: 'AGO');

        do {
            $number = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
