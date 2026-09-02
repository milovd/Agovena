<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Agovena\Cart\CartService;
use App\Agovena\Cart\TokenCartRepository;
use App\Agovena\Checkout\CheckoutCountries;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\DeleteCustomerAddress;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Http\Resources\Api\V1\AddressResource;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class AccountController
{
    public function addresses(CustomerPropertyService $properties): AnonymousResourceCollection
    {
        $customer = authenticated_customer();
        $addresses = $customer->addresses()->latest('id')->get();
        if ($addresses->isEmpty()) {
            $propertyAddress = $properties->addressFromProperties($customer);
            if ($propertyAddress !== null) {
                return AddressResource::collection(collect([$propertyAddress]));
            }
        }

        return AddressResource::collection($addresses);
    }

    public function storeAddress(Request $request, SaveCustomerAddress $save, CustomerPropertyService $properties): AddressResource
    {
        $data = $this->validatedAddress($request);
        $customer = authenticated_customer();
        $hasAddresses = $customer->addresses()->exists();
        $addressData = $this->addressData($data);
        $address = $save->handle(
            $customer,
            $addressData,
            [
                'label' => $data['label'] ?? null,
                'is_default_billing' => (bool) ($data['is_default_billing'] ?? false),
                'is_default_shipping' => (bool) ($data['is_default_shipping'] ?? false),
            ],
        );
        if (! $hasAddresses || $address->is_default_billing) {
            $properties->saveAddressProperties($customer, $addressData, 'customer');
        }

        return new AddressResource($address);
    }

    public function updateAddress(Request $request, CustomerAddress $address, SaveCustomerAddress $save, CustomerPropertyService $properties): AddressResource
    {
        $this->assertOwned($address);
        $data = $this->validatedAddress($request);
        $customer = authenticated_customer();
        $addressData = $this->addressData($data);
        $updated = $save->handle(
            $customer,
            $addressData,
            [
                'label' => $data['label'] ?? null,
                'is_default_billing' => (bool) ($data['is_default_billing'] ?? false),
                'is_default_shipping' => (bool) ($data['is_default_shipping'] ?? false),
            ],
            $address,
        );
        if ($updated->is_default_billing) {
            $properties->saveAddressProperties($customer, $addressData, 'customer');
        }

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
            'properties' => ['nullable', 'array'],
            'properties.phone' => ['nullable', 'string', 'max:40'],
            'properties.company_name' => ['nullable', 'string', 'max:255'],
            'properties.address' => ['nullable', 'string', 'max:255'],
            'properties.address2' => ['nullable', 'string', 'max:255'],
            'properties.city' => ['nullable', 'string', 'max:120'],
            'properties.state' => ['nullable', 'string', 'max:120'],
            'properties.zip' => ['nullable', 'string', 'max:20'],
            'properties.country' => ['nullable', 'string', 'size:2', Rule::in(CheckoutCountries::codes())],
            'is_default_billing' => ['sometimes', 'boolean'],
            'is_default_shipping' => ['sometimes', 'boolean'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function addressData(array $data): AddressData
    {
        $propertyValues = is_array($data['properties'] ?? null) ? $data['properties'] : [];

        return AddressData::fromArray([
            'name' => $data['name'],
            'company' => $propertyValues['company_name'] ?? ($data['company'] ?? null),
            'line1' => $propertyValues['address'] ?? $data['line1'],
            'line2' => $propertyValues['address2'] ?? ($data['line2'] ?? null),
            'city' => $propertyValues['city'] ?? $data['city'],
            'region' => $propertyValues['state'] ?? ($data['region'] ?? null),
            'postal_code' => $propertyValues['zip'] ?? $data['postal_code'],
            'country' => $propertyValues['country'] ?? $data['country'],
            'phone' => $propertyValues['phone'] ?? ($data['phone'] ?? null),
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
