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
use App\Agovena\Payments\PaymentFeePolicy;
use App\Agovena\Referrals\ReferralService;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Tax\TaxCalculator;
use App\Agovena\Tax\TaxRateResolver;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Events\OrderPlacing;
use App\Events\OrderPreflight;
use App\Models\Customer;
use App\Models\DiscountRedemption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Database\QueryException;
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
        private readonly PaymentFeePolicy $paymentFees,
        private readonly ShippingQuoteResolver $shippingQuotes,
        private readonly DiscountApplicator $discounts,
        private readonly TaxCalculator $taxes,
        private readonly TaxRateResolver $taxRates,
        private readonly ApplyCreditToOrder $applyCredit,
        private readonly ReferralService $referrals,
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
     *     referral_code?: string|null,
     *     referral_visit_id?: int|null,
     *     apply_credit?: bool,
     *     credit_amount?: int|null,
     *     custom_properties?: array<string, mixed>
     * }  $guest
     */
    public function handle(array $guest): Order
    {
        $idempotencyKey = $guest['idempotency_key'] ?? null;
        $customerId = isset($guest['customer_id']) ? (int) $guest['customer_id'] : null;
        if ($customerId !== null && $customerId < 1) {
            $customerId = null;
        }
        $idempotencyOwnerHash = $this->idempotencyOwnerHash($customerId);

        if (filled($idempotencyKey)) {
            $existing = $this->existingIdempotentOrder($idempotencyKey, $idempotencyOwnerHash, $customerId);
            if ($existing !== null) {
                return $this->resumeIdempotentOrder($existing);
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
        $referralCode = is_string($guest['referral_code'] ?? null) ? trim($guest['referral_code']) : '';
        $referralVisitId = isset($guest['referral_visit_id']) ? (int) $guest['referral_visit_id'] : null;

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
        $taxRate = $this->taxRates->resolve($taxCountry);
        $pricesIncludeTax = (bool) $this->settings->get('store', 'prices_include_tax', false);
        $tax = $this->taxes->calculate(
            $subtotalAfterDiscount,
            $shippingAmount,
            $pricesIncludeTax,
            $taxRate,
        );
        $total = $subtotalAfterDiscount->add($shippingAmount);
        if (! $pricesIncludeTax) {
            $total = $total->add($tax->tax);
        }

        $preflight = new OrderPreflight($lines);
        event($preflight);

        try {
            $order = DB::transaction(function () use (
                $preflight,
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
                $idempotencyOwnerHash,
                $method,
                $referralCode,
                $referralVisitId,
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
                    'idempotency_owner_hash' => $idempotencyOwnerHash,
                    'shipping_same_as_billing' => $shippingSame,
                ];

                if ($billing instanceof AddressData) {
                    $payload = [...$payload, ...$billing->toOrderBillingColumns()];
                }

                if ($shipping instanceof AddressData) {
                    $payload = [...$payload, ...$shipping->toOrderShippingColumns()];
                }

                $propertyOverlay = is_array($guest['custom_properties'] ?? null) ? $guest['custom_properties'] : [];
                unset($propertyOverlay['origin']);
                if ($billing instanceof AddressData) {
                    $propertyOverlay = [
                        ...$propertyOverlay,
                        ...$this->properties->addressPropertyValues($billing),
                    ];
                }
                $customer = $customerId !== null ? Customer::query()->find($customerId) : null;
                if ($customer !== null && $propertyOverlay !== []) {
                    $this->properties->save(
                        $customer,
                        $this->properties->definitionsFor('checkout'),
                        $propertyOverlay,
                        'customer',
                    );
                }
                $payload['custom_properties_snapshot'] = array_values(array_filter(
                    $this->properties->snapshot($customer, $propertyOverlay, invoiceOnly: true),
                    static fn (array $property): bool => $property['key'] !== 'origin',
                ));

                $order = Order::query()->create($payload);
                if ($referralCode !== '') {
                    $this->referrals->attribute($order, $referralCode, $referralVisitId);
                }

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
                $paymentFee = $this->paymentFees->calculate(
                    $resolvedMethod,
                    Money::of($amountDue, $subtotal->currency),
                );
                $amountDue += $paymentFee->amount;
                $order->update([
                    'total_amount' => $total->amount + $paymentFee->amount,
                    'payment_fee_amount' => $paymentFee->amount,
                    'payment_fee_snapshot' => $paymentFee->snapshot,
                ]);

                foreach ($lines as $line) {
                    $product = Product::query()->find($line->productId);
                    $item = OrderItem::query()->create([
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
                    if ($product !== null) {
                        $this->optionPricer->storeRuntimeSecrets($item->id, $product, $line->selections);
                    }
                }
                event(new OrderPlacing($lines, $order, $preflight));

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
                event(new OrderCreated($order, $shippingMethodId, $preflight));

                return $order;
            });
        } catch (QueryException $exception) {
            if (filled($idempotencyKey) && $this->isIdempotencyUniqueViolation($exception)) {
                $existing = $this->existingIdempotentOrder($idempotencyKey, $idempotencyOwnerHash, $customerId);
                if ($existing !== null) {
                    return $this->resumeIdempotentOrder($existing);
                }
            }

            throw $exception;
        }

        $this->cart->clear();

        $paymentMethod = (string) ($order->payment->method ?? '');

        if ($paymentMethod === 'account_balance' || (int) ($order->payment->amount ?? 0) === 0) {
            $this->accountBalancePayment->handle($order);

            return $order->fresh(['items', 'payment']) ?? $order;
        }

        if ($paymentMethod === 'development') {
            $this->developmentPayment->handle($order);

            return $order->fresh(['items', 'payment']) ?? $order;
        }

        return $order;
    }

    private function resumeIdempotentOrder(Order $order): Order
    {
        $payment = $order->payment;
        if ($payment === null
            || ! $order->isRetryablePayment()
            || in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true)
        ) {
            return $order;
        }

        if ($payment->method === 'account_balance' || (int) $payment->amount === 0) {
            $this->accountBalancePayment->handle($order);
        } elseif ($payment->method === 'development') {
            $this->developmentPayment->handle($order);
        }

        return $order->fresh(['items', 'payment']) ?? $order;
    }

    private function idempotencyOwnerHash(?int $customerId): string
    {
        $owner = $customerId !== null
            ? 'customer|'.$customerId
            : 'session|'.session()->getId();

        return hash('sha256', $owner);
    }

    private function existingIdempotentOrder(string $idempotencyKey, string $ownerHash, ?int $customerId): ?Order
    {
        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing === null) {
            return null;
        }

        if ($existing->idempotency_owner_hash === null) {
            if ($customerId === null || (int) $existing->customer_id !== $customerId) {
                throw ValidationException::withMessages([
                    'idempotency_key' => __('api.checkout_failed'),
                ]);
            }

            $existing->forceFill(['idempotency_owner_hash' => $ownerHash])->save();
        } elseif (! hash_equals($existing->idempotency_owner_hash, $ownerHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('api.checkout_failed'),
            ]);
        }

        return $existing->load(['items', 'payment']);
    }

    private function isIdempotencyUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true)
            && str_contains(strtolower($exception->getMessage()), 'idempotency');
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
}
