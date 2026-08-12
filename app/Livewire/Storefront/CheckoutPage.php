<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\AddressValidation;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Checkout\ShippingQuoteResolver;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\CustomerRegistration;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Agovena\Discounts\DiscountApplicator;
use App\Agovena\Money\Money;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Tax\TaxCalculator;
use App\Agovena\Theme\ThemeManager;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class CheckoutPage extends Component
{
    public string $customer_name = '';

    public string $customer_email = '';

    public string $idempotency_key = '';

    public string $payment_method = 'manual';

    public ?int $shipping_method_id = null;

    public string $billing_name = '';

    public string $billing_company = '';

    public string $billing_line1 = '';

    public string $billing_line2 = '';

    public string $billing_city = '';

    public string $billing_region = '';

    public string $billing_postal_code = '';

    public string $billing_country = 'NL';

    public string $billing_phone = '';

    public bool $shipping_same_as_billing = true;

    public string $shipping_name = '';

    public string $shipping_company = '';

    public string $shipping_line1 = '';

    public string $shipping_line2 = '';

    public string $shipping_city = '';

    public string $shipping_region = '';

    public string $shipping_postal_code = '';

    public string $shipping_country = 'NL';

    public string $shipping_phone = '';

    public bool $save_billing_address = false;

    public string $coupon_code = '';

    public string $applied_coupon_code = '';

    public bool $apply_credit = false;

    public function mount(CartService $cart, CustomerRegistration $registration): void
    {
        if ($cart->isEmpty()) {
            $this->redirect(route('storefront.cart'), navigate: true);

            return;
        }

        if ($registration->requiresAccountForCheckout() && ! Auth::check()) {
            session()->put('url.intended', route('storefront.checkout'));
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $this->idempotency_key = (string) Str::uuid();
        $this->payment_method = PaymentMethod::Manual->value;

        /** @var Customer|null $customer */
        $customer = current_customer();
        if ($customer !== null) {
            $this->customer_name = $customer->name;
            $this->customer_email = $customer->email;

            /** @var CustomerAddress|null $defaultBilling */
            $defaultBilling = $customer->addresses()
                ->where('is_default_billing', true)
                ->first()
                ?? $customer->addresses()->latest('id')->first();

            if ($defaultBilling !== null) {
                $this->fillBillingFromAddress($defaultBilling);
            } else {
                $this->billing_name = $customer->name;
            }
        }
    }

    public function placeOrder(PlaceOrder $placeOrder, CustomerRegistration $registration, CartService $cart, SaveCustomerAddress $saveAddress): void
    {
        if ($registration->requiresAccountForCheckout() && ! Auth::check()) {
            session()->put('url.intended', route('storefront.checkout'));
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $allowed = app(AvailablePaymentMethods::class)->ids();

        $rules = [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'payment_method' => ['required', 'string', Rule::in($allowed)],
            ...AddressValidation::rules('billing'),
            'save_billing_address' => ['boolean'],
            'apply_credit' => ['boolean'],
        ];

        $requiresShipping = $cart->requiresShipping();
        if ($requiresShipping) {
            $rules['shipping_same_as_billing'] = ['boolean'];
            $rules['shipping_method_id'] = ['required', 'integer'];
            if (! $this->shipping_same_as_billing) {
                $rules = [...$rules, ...AddressValidation::rules('shipping')];
            }
        }

        $data = $this->validate($rules);

        $billing = AddressData::fromArray([
            'name' => $data['billing_name'],
            'company' => $data['billing_company'] ?? null,
            'line1' => $data['billing_line1'],
            'line2' => $data['billing_line2'] ?? null,
            'city' => $data['billing_city'],
            'region' => $data['billing_region'] ?? null,
            'postal_code' => $data['billing_postal_code'],
            'country' => $data['billing_country'],
            'phone' => $data['billing_phone'] ?? null,
        ]);

        $shipping = null;
        $shippingSame = true;
        if ($requiresShipping) {
            $shippingSame = (bool) ($data['shipping_same_as_billing'] ?? true);
            if (! $shippingSame) {
                $shipping = AddressData::fromArray([
                    'name' => $data['shipping_name'],
                    'company' => $data['shipping_company'] ?? null,
                    'line1' => $data['shipping_line1'],
                    'line2' => $data['shipping_line2'] ?? null,
                    'city' => $data['shipping_city'],
                    'region' => $data['shipping_region'] ?? null,
                    'postal_code' => $data['shipping_postal_code'],
                    'country' => $data['shipping_country'],
                    'phone' => $data['shipping_phone'] ?? null,
                ]);
            }
        }

        /** @var Customer|null $customer */
        $customer = current_customer();
        if ($customer !== null && ($data['save_billing_address'] ?? false)) {
            $saveAddress->handle($customer, $billing, [
                'label' => __('customer.addresses.checkout_saved_label'),
                'is_default_billing' => true,
            ]);
        }

        $order = $placeOrder->handle([
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'idempotency_key' => $data['idempotency_key'],
            'payment_method' => $data['payment_method'],
            'customer_id' => current_customer()?->id,
            'billing' => $billing,
            'shipping' => $shipping,
            'shipping_same_as_billing' => $shippingSame,
            'shipping_method_id' => $requiresShipping ? (int) ($data['shipping_method_id'] ?? 0) : null,
            'discount_code' => $this->applied_coupon_code !== '' ? $this->applied_coupon_code : null,
            'apply_credit' => (bool) ($data['apply_credit'] ?? false),
        ]);

        $this->redirect(route('storefront.order.confirmation', $order), navigate: true);
    }

    public function applyCoupon(CartService $cart, DiscountApplicator $discounts): void
    {
        $subtotal = $cart->subtotal();
        if ($subtotal === null) {
            return;
        }

        $applied = $discounts->apply($this->coupon_code, $subtotal, current_customer()?->id);
        $this->applied_coupon_code = $applied?->code->code ?? '';
        $this->coupon_code = $this->applied_coupon_code;
        $this->resetErrorBag('discount_code');
    }

    public function removeCoupon(): void
    {
        $this->coupon_code = '';
        $this->applied_coupon_code = '';
        $this->resetErrorBag('discount_code');
    }

    public function render(
        CartService $cart,
        ThemeManager $themes,
        CustomerRegistration $registration,
        ShippingQuoteResolver $shippingQuotes,
        DiscountApplicator $discounts,
        TaxCalculator $taxes,
        SettingsRepository $settings,
        CustomerCreditLedger $creditLedger,
    ) {
        $theme = $themes->active();
        $lines = $cart->pricedLines();
        $subtotal = $cart->subtotal();
        $requiresShipping = $cart->requiresShipping();
        /** @var Customer|null $customer */
        $customer = current_customer();
        $creditBalance = $customer === null || $subtotal === null
            ? 0
            : $creditLedger->balance($customer, $subtotal->currency);

        $quotes = [];
        $shippingTotal = null;
        if ($requiresShipping && $subtotal !== null) {
            $country = $this->shipping_same_as_billing
                ? $this->billing_country
                : $this->shipping_country;
            $quotes = $shippingQuotes->quotes(
                $cart->shippableLines(),
                strtoupper($country !== '' ? $country : 'NL'),
                $subtotal->currency,
            );
            if ($this->shipping_method_id === null && $quotes !== []) {
                $this->shipping_method_id = $quotes[0]->methodId;
            }
            foreach ($quotes as $quote) {
                if ($quote->methodId === $this->shipping_method_id) {
                    $shippingTotal = $quote->amount;
                    break;
                }
            }
        }

        $discountTotal = null;
        $taxTotal = null;
        $creditTotal = null;
        $orderTotal = $subtotal;
        $pricesIncludeTax = (bool) $settings->get('store', 'prices_include_tax', false);
        if ($subtotal !== null) {
            $applied = $discounts->apply(
                $this->applied_coupon_code !== '' ? $this->applied_coupon_code : null,
                $subtotal,
                current_customer()?->id,
            );
            $discountTotal = $applied === null ? Money::of(0, $subtotal->currency) : $applied->amount;
            $subtotalAfterDiscount = $subtotal->subtract($discountTotal);
            $shipping = $shippingTotal ?? Money::of(0, $subtotal->currency);
            $country = $requiresShipping && ! $this->shipping_same_as_billing
                ? $this->shipping_country
                : $this->billing_country;
            $tax = $taxes->calculate(
                $subtotalAfterDiscount,
                $shipping,
                $country !== '' ? $country : 'NL',
                $pricesIncludeTax,
                $this->activeTaxRate($country !== '' ? $country : 'NL'),
            );
            $taxTotal = $tax->tax;
            $orderTotal = $subtotalAfterDiscount->add($shipping);
            if (! $pricesIncludeTax) {
                $orderTotal = $orderTotal->add($taxTotal);
            }
            if ($this->apply_credit && $creditBalance > 0) {
                $creditTotal = Money::of(min($creditBalance, $orderTotal->amount), $orderTotal->currency);
            }
        }

        return view($theme->view('checkout.index'), [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'shippingQuotes' => $quotes,
            'shippingTotal' => $shippingTotal,
            'discountTotal' => $discountTotal,
            'taxTotal' => $taxTotal,
            'orderTotal' => $orderTotal,
            'creditTotal' => $creditTotal,
            'theme' => $theme,
            'paymentOptions' => app(AvailablePaymentMethods::class)->options(),
            'developmentPayEnabled' => $this->developmentPayEnabled(),
            'customerLoggedIn' => Auth::check(),
            'registrationEnabled' => $registration->allowsRegistration(),
            'requiresShipping' => $requiresShipping,
            'pricesIncludeTax' => $pricesIncludeTax,
            'creditBalance' => $creditBalance,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('storefront.checkout.title'),
            'theme' => $theme,
        ]);
    }

    private function fillBillingFromAddress(CustomerAddress $address): void
    {
        $this->billing_name = $address->name;
        $this->billing_company = (string) ($address->company ?? '');
        $this->billing_line1 = $address->line1;
        $this->billing_line2 = (string) ($address->line2 ?? '');
        $this->billing_city = $address->city;
        $this->billing_region = (string) ($address->region ?? '');
        $this->billing_postal_code = $address->postal_code;
        $this->billing_country = $address->country;
        $this->billing_phone = (string) ($address->phone ?? '');
    }

    private function developmentPayEnabled(): bool
    {
        return (bool) config('agovena.payments.allow_development_instant_pay')
            && ! app()->environment('production');
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
