<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Agovena\Cart\CartService;
use App\Agovena\Cart\TokenCartRepository;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\DeleteCustomerAddress;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Http\Resources\Api\V1\AddressResource;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AccountController
{
    public function addresses(): AnonymousResourceCollection
    {
        $customer = authenticated_customer();

        return AddressResource::collection($customer->addresses()->latest('id')->get());
    }

    public function storeAddress(Request $request, SaveCustomerAddress $save): AddressResource
    {
        $data = $this->validatedAddress($request);
        $address = $save->handle(
            authenticated_customer(),
            AddressData::fromArray($data),
            [
                'label' => $data['label'] ?? null,
                'is_default_billing' => (bool) ($data['is_default_billing'] ?? false),
                'is_default_shipping' => (bool) ($data['is_default_shipping'] ?? false),
            ],
        );

        return new AddressResource($address);
    }

    public function updateAddress(Request $request, CustomerAddress $address, SaveCustomerAddress $save): AddressResource
    {
        $this->assertOwned($address);
        $data = $this->validatedAddress($request);
        $updated = $save->handle(
            authenticated_customer(),
            AddressData::fromArray($data),
            [
                'label' => $data['label'] ?? null,
                'is_default_billing' => (bool) ($data['is_default_billing'] ?? false),
                'is_default_shipping' => (bool) ($data['is_default_shipping'] ?? false),
            ],
            $address,
        );

        return new AddressResource($updated);
    }

    public function destroyAddress(CustomerAddress $address, DeleteCustomerAddress $delete): JsonResponse
    {
        $this->assertOwned($address);
        $delete->handle(authenticated_customer(), $address);

        return response()->json(['ok' => true]);
    }

    public function cart(CartService $cart, TokenCartRepository $tokens): JsonResponse
    {
        return $this->cartPayload($cart, $tokens);
    }

    public function addToCart(Request $request, CartService $cart, TokenCartRepository $tokens): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'selections' => ['nullable', 'array'],
        ]);

        $cart->add(
            (int) $data['product_id'],
            (int) ($data['quantity'] ?? 1),
            is_array($data['selections'] ?? null) ? $data['selections'] : [],
        );

        return $this->cartPayload($cart, $tokens);
    }

    public function updateCartLine(Request $request, string $lineKey, CartService $cart, TokenCartRepository $tokens): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);
        $cart->update($lineKey, (int) $data['quantity']);

        return $this->cartPayload($cart, $tokens);
    }

    public function removeCartLine(string $lineKey, CartService $cart, TokenCartRepository $tokens): JsonResponse
    {
        $cart->remove($lineKey);

        return $this->cartPayload($cart, $tokens);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_default_billing' => ['sometimes', 'boolean'],
            'is_default_shipping' => ['sometimes', 'boolean'],
        ]);
    }

    private function assertOwned(CustomerAddress $address): void
    {
        abort_unless((int) $address->customer_id === (int) authenticated_customer()->id, 404);
    }

    private function cartPayload(CartService $cart, TokenCartRepository $tokens): JsonResponse
    {
        $lines = [];
        foreach ($cart->pricedLines() as $line) {
            $lines[] = [
                'line_key' => $line->lineKey,
                'product_id' => $line->productId,
                'label' => $line->label,
                'quantity' => $line->quantity,
                'unit_amount' => $line->unitPrice->amount,
                'line_total_amount' => $line->lineTotal->amount,
                'currency' => $line->unitPrice->currency,
                'selections' => $line->selections,
            ];
        }

        $subtotal = $cart->subtotal();

        return response()->json([
            'data' => [
                'token' => $tokens->token(),
                'item_count' => $cart->itemCount(),
                'requires_shipping' => $cart->requiresShipping(),
                'subtotal_amount' => $subtotal?->amount,
                'currency' => $subtotal?->currency,
                'lines' => $lines,
            ],
        ])->header('X-Cart-Token', $tokens->token());
    }
}
