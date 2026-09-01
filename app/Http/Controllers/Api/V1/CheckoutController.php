<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Agovena\Api\ApiError;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\CartRequirementComposer;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\StartOrderPayment;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class CheckoutController
{
    public function requirements(
        CartService $cart,
        CartRequirementComposer $composer,
        AvailablePaymentMethods $methods,
    ): JsonResponse {
        $requirements = $composer->compose($cart);

        return response()->json([
            'data' => [
                'requirements' => $requirements->ids(),
                'requires_shipping' => $requirements->requiresShipping(),
                'payment_methods' => $methods->options(),
            ],
        ]);
    }

    public function store(Request $request, PlaceOrder $place): OrderResource
    {
        $customer = authenticated_customer();
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:40'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'billing' => ['required', 'array'],
            'billing.name' => ['required', 'string', 'max:255'],
            'billing.line1' => ['required', 'string', 'max:255'],
            'billing.city' => ['required', 'string', 'max:120'],
            'billing.postal_code' => ['required', 'string', 'max:20'],
            'billing.country' => ['required', 'string', 'size:2'],
            'billing.company' => ['nullable', 'string', 'max:255'],
            'billing.line2' => ['nullable', 'string', 'max:255'],
            'billing.region' => ['nullable', 'string', 'max:120'],
            'billing.phone' => ['nullable', 'string', 'max:40'],
            'shipping' => ['nullable', 'array'],
            'shipping_same_as_billing' => ['sometimes', 'boolean'],
            'shipping_method_id' => ['nullable', 'integer'],
            'discount_code' => ['nullable', 'string', 'max:40'],
        ]);

        $order = $place->handle([
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_id' => $customer->id,
            'payment_method' => $data['payment_method'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'billing' => AddressData::fromArray($data['billing']),
            'shipping' => isset($data['shipping']) && is_array($data['shipping'])
                ? AddressData::fromArray($data['shipping'])
                : null,
            'shipping_same_as_billing' => (bool) ($data['shipping_same_as_billing'] ?? true),
            'shipping_method_id' => isset($data['shipping_method_id']) ? (int) $data['shipping_method_id'] : null,
            'discount_code' => $data['discount_code'] ?? null,
        ]);

        return new OrderResource($order->load(['items', 'payment', 'invoice', 'creditNotes']));
    }

    public function pay(Request $request, Order $order, StartOrderPayment $start): JsonResponse
    {
        $this->assertOwned($order);
        if (! $request->has('idempotency_key') && $request->headers->has('Idempotency-Key')) {
            $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        }

        $data = $request->validate([
            'gateway' => ['required', 'string', 'max:40'],
            'return_url' => ['required', 'url', 'max:500'],
            'cancel_url' => ['required', 'url', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $attempt = $start->handle(
                $order,
                $data['gateway'],
                $data['return_url'],
                $data['cancel_url'],
                $data['idempotency_key'] ?? null,
            );
        } catch (ValidationException $e) {
            return ApiError::json('payment_failed', $e->getMessage(), 422, $e->errors());
        }

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->id,
                'status' => $attempt->status->value,
                'redirect_url' => $attempt->redirect_url,
                'payment_status' => $order->fresh()?->payment?->status->value,
            ],
        ]);
    }

    private function assertOwned(Order $order): void
    {
        abort_unless((int) $order->customer_id === (int) authenticated_customer()->id, 404);
    }
}
