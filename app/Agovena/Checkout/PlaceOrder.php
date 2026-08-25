<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Credits\ApplyCreditToOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Discounts\DiscountApplicator;
use App\Agovena\Money\Money;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\CheckoutPaymentSelection;
use App\Agovena\Payments\CompleteAccountBalancePayment;
use App\Agovena\Payments\CompleteDevelopmentPayment;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Tax\TaxCalculator;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Events\OrderPlacing;
use App\Models\Customer;
use App\Models\DiscountRedemption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlaceOrder
{
    public function __construct(
        private readonly CartService $cart,
        private readonly SettingsRepository $settings,
        private readonly CompleteDevelopmentPayment $developmentPayment,
        private readonly CompleteAccountBalancePayment $accountBalancePayment,
        private readonly ShippingQuoteResolver $shippingQuotes,
        private readonly DiscountApplicator $discounts,
        private readonly TaxCalculator $taxes,
        private readonly ApplyCreditToOrder $applyCredit,
        private readonly ProductOptionPricer $optionPricer,
        private readonly CustomerPropertyService $properties,
        private readonly CartRequirementComposer $requirements,
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
     *     shipping_same_as_billing?: bool,
     *     shipping_method_id?: int|null,
     *     shipping_quote_key?: string|null,
     *     discount_code?: string|null,
     *     apply_credit?: bool,
     *     credit_amount?: int|null,
     *     custom_properties?: array<string, mixed>
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

        $this->cart->assertConfigured();

        $lines = $this->cart->pricedLines();
        $subtotal = $this->cart->subtotal();

        if ($subtotal === null) {
            throw ValidationException::withMessages([
                'cart' => __('storefront.errors.cart_empty'),
            ]);
        }

        $method = $guest['payment_method'] ?? null;
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

        $shippingAmount = Money::of(0, $subtotal->currency);
        $shippingLabel = null;
        $shippingMethodId = isset($guest['shipping_method_id']) ? (int) $guest['shipping_method_id'] : null;
        $shippingCarrierId = null;
        $shippingServiceCode = null;
        $quoteKey = is_string($guest['shipping_quote_key'] ?? null)
            ? trim($guest['shipping_quote_key'])
            : '';

        $requirements = $this->requirements->compose($this->cart);
        if ($requirements->requiresShipping()) {
            if ($shipping === null) {
                throw ValidationException::withMessages([
                    'shipping' => __('storefront.errors.shipping_address_required'),
                ]);
            }

            $country = strtoupper($shipping->country);
            $destination = new ShippingDestination(
                country: $country,
                postalCode: $shipping->postalCode,
                city: $shipping->city,
                line1: $shipping->line1,
            );

            if ($quoteKey === '' && ($shippingMethodId === null || $shippingMethodId < 1)) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => __('storefront.errors.shipping_method_required'),
                ]);
            }

            $quote = $quoteKey !== ''
                ? $this->shippingQuotes->quoteByKey(
                    $this->cart->shippableLines(),
                    $country,
                    $subtotal->currency,
                    $quoteKey,
                    $destination,
                )
                : $this->shippingQuotes->quote(
                    $this->cart->shippableLines(),
                    $country,
                    $subtotal->currency,
                    (int) $shippingMethodId,
                    $destination,
                );

            if ($quote === null) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => __('storefront.errors.shipping_method_unavailable'),
                ]);
            }

            $shippingAmount = $quote->amount;
            $shippingLabel = $quote->label;
            $shippingMethodId = $quote->isCarrierQuote() ? null : $quote->methodId;
            $shippingCarrierId = $quote->carrierId;
            $shippingServiceCode = $quote->serviceCode;
        }

        $discount = $this->discounts->apply($guest['discount_code'] ?? null, $subtotal, $customerId);
        $subtotalAfterDiscount = $subtotal->subtract(
            $discount === null ? Money::of(0, $subtotal->currency) : $discount->amount,
        );
        $taxCountry = $shipping?->country ?: ($billing?->country ?: 'NL');
        $taxRate = $this->activeTaxRate($taxCountry);
        $pricesIncludeTax = (bool) $this->settings->get('store', 'prices_include_tax', false);
        $tax = $this->taxes->calculate(
            $subtotalAfterDiscount,
            $shippingAmount,
            $taxCountry,
            $pricesIncludeTax,
            $taxRate,
        );
        $total = $subtotalAfterDiscount->add($shippingAmount);
        if (! $pricesIncludeTax) {
            $total = $total->add($tax->tax);
        }

        $order = DB::transaction(function () use (
            $guest,
            $lines,
            $subtotal,
            $total,
            $shippingAmount,
            $shippingLabel,
            $shippingMethodId,
            $shippingCarrierId,
            $shippingServiceCode,
            $discount,
            $tax,
            $idempotencyKey,
            $method,
            $customerId,
            $billing,
            $shipping,
            $shippingSame,
        ): Order {
            $payload = [
                'number' => $this->generateNumber(),
                'status' => OrderStatus::Pending,
                'customer_name' => $guest['customer_name'],
                'customer_email' => $guest['customer_email'],
                'customer_id' => $customerId,
                'subtotal_amount' => $subtotal->amount,
                'shipping_amount' => $shippingAmount->amount,
                'shipping_method_label' => $shippingLabel,
                'shipping_carrier_id' => $shippingCarrierId,
                'shipping_service_code' => $shippingServiceCode,
                'discount_amount' => $discount?->amount->amount ?? 0,
                'tax_amount' => $tax->tax->amount,
                'credit_amount' => 0,
                'discount_code' => $discount?->code->code,
                'tax_rate_name' => $tax->rateName,
                'tax_rate_bps' => $tax->rateBps,
                'total_amount' => $total->amount,
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

            $propertyOverlay = is_array($guest['custom_properties'] ?? null) ? $guest['custom_properties'] : [];
            $customer = $customerId !== null ? Customer::query()->find($customerId) : null;
            if ($customer !== null && $propertyOverlay !== []) {
                $this->properties->save(
                    $customer,
                    $this->properties->definitionsFor('checkout'),
                    $propertyOverlay,
                    'customer',
                );
            }
            $payload['custom_properties_snapshot'] = $this->properties->snapshot($customer, $propertyOverlay, invoiceOnly: true);

            $order = Order::query()->create($payload);

            $creditAmount = 0;
            if (($guest['apply_credit'] ?? false) && $customerId !== null) {
                $customer = Customer::query()->findOrFail($customerId);
                $creditAmount = $this->applyCredit->handle(
                    $order,
                    $customer,
                    isset($guest['credit_amount']) ? (int) $guest['credit_amount'] : $total->amount,
                );
                if ($creditAmount > 0) {
                    $order->update(['credit_amount' => $creditAmount]);
                }
            }

            $amountDue = $total->amount - $creditAmount;
            $resolvedMethod = $this->resolvePaymentMethod(
                is_string($method) ? $method : null,
                $amountDue,
            );

            foreach ($lines as $line) {
                $product = Product::query()->find($line->productId);
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line->productId,
                    'label' => $line->label,
                    'quantity' => $line->quantity,
                    'unit_amount' => $line->unitPrice->amount,
                    'line_total_amount' => $line->lineTotal->amount,
                    'currency' => $line->unitPrice->currency,
                    'options_snapshot' => $product === null
                        ? []
                        : $this->optionPricer->snapshot($product, $line->selections),
                ]);
            }

            Payment::query()->create([
                'order_id' => $order->id,
                'amount' => $amountDue,
                'currency' => $subtotal->currency,
                'method' => $resolvedMethod,
                'status' => PaymentStatus::Pending,
                'paid_at' => null,
                'reference' => null,
            ]);

            if ($discount !== null) {
                DiscountRedemption::query()->create([
                    'discount_code_id' => $discount->code->id,
                    'order_id' => $order->id,
                    'customer_id' => $customerId,
                    'code' => $discount->code->code,
                    'amount' => $discount->amount->amount,
                ]);
            }

            $order = $order->load(['items', 'payment']);

            // Inside the transaction so Module listeners (e.g. inventory/shipping) can roll back on failure.
            event(new OrderCreated($order, $shippingMethodId));

            return $order;
        });

        $this->cart->clear();

        $paymentMethod = (string) ($order->payment?->method ?? '');

        if ($paymentMethod === 'account_balance' || (int) ($order->payment?->amount ?? 0) === 0) {
            $this->accountBalancePayment->handle($order);

            return $order->fresh(['items', 'payment']) ?? $order;
        }

        if ($paymentMethod === 'development') {
            $this->developmentPayment->handle($order);

            return $order->fresh(['items', 'payment']) ?? $order;
        }

        return $order;
    }

    private function resolvePaymentMethod(?string $value, int $amountDue): string
    {
        if ($amountDue < 1) {
            return 'account_balance';
        }

        $allowed = app(AvailablePaymentMethods::class)->ids();
        if ($allowed === []) {
            throw ValidationException::withMessages([
                'payment_method' => __('storefront.errors.payment_gateway_required'),
            ]);
        }

        if ($value === null || trim($value) === '') {
            $value = $allowed[0];
        }

        $selection = CheckoutPaymentSelection::parse($value);
        $gatewayId = $selection->gatewayId;

        if ($gatewayId === 'development') {
            $devAllowed = (bool) config('agovena.payments.allow_development_instant_pay');
            if (! $devAllowed || app()->environment('production')) {
                throw ValidationException::withMessages([
                    'payment_method' => __('storefront.errors.development_payment_unavailable'),
                ]);
            }

            return 'development';
        }

        if ($gatewayId === 'account_balance') {
            throw ValidationException::withMessages([
                'payment_method' => __('storefront.errors.payment_method_unavailable'),
            ]);
        }

        $optionIds = $allowed;
        $gatewayIds = array_values(array_unique(array_map(
            static fn (string $id): string => CheckoutPaymentSelection::parse($id)->gatewayId,
            $optionIds,
        )));

        if (in_array($value, $optionIds, true) || in_array($gatewayId, $gatewayIds, true)) {
            return $gatewayId;
        }

        throw ValidationException::withMessages([
            'payment_method' => __('storefront.errors.payment_method_unavailable'),
        ]);
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

    private function activeTaxRate(string $country): ?TaxRate
    {
        return TaxRate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($country): void {
                $query->where('country', strtoupper($country))
                    ->orWhereNull('country');
            })
            ->orderByRaw('CASE WHEN country IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->first();
    }
}
