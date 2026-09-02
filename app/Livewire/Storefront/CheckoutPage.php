<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\AddressAutocomplete\AddressAutocomplete;
use App\Agovena\Checkout\AddressAutocomplete\AddressSuggestion;
use App\Agovena\Checkout\AddressAutocomplete\ResolvedAddress;
use App\Agovena\Checkout\AddressValidation;
use App\Agovena\Checkout\CartRequirement;
use App\Agovena\Checkout\CartRequirementComposer;
use App\Agovena\Checkout\CartRequirements;
use App\Agovena\Checkout\CheckoutCountries;
use App\Agovena\Checkout\CheckoutFlow;
use App\Agovena\Checkout\CheckoutStep;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Checkout\ShippingDestination;
use App\Agovena\Checkout\ShippingQuoteResolver;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\CustomerRegistration;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Agovena\Discounts\DiscountApplicator;
use App\Agovena\Money\Money;
use App\Agovena\Orders\StorefrontOrderAccess;
use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\CheckoutPaymentSelection;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\StartOrderPayment;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Tax\TaxCalculator;
use App\Agovena\Tax\TaxRateResolver;
use App\Agovena\Theme\ThemeManager;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\SuggestsAddresses;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\MoneyFormatter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

final class CheckoutPage extends Component
{
    use SuggestsAddresses;

    public string $customer_name = '';

    public string $customer_email = '';

    public string $idempotency_key = '';

    #[Url(as: 'step', history: true)]
    public string $step = 'details';

    /** @var list<string> */
    public array $completedSteps = [];

    public string $payment_method = '';

    public ?int $shipping_method_id = null;

    public string $shipping_quote_key = '';

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

    /** @var array<string, mixed> */
    public array $propertyValues = [];

    public function mount(CartService $cart, CustomerRegistration $registration, CustomerPropertyService $properties): void
    {
        if ($cart->isEmpty()) {
            throw new HttpResponseException(new RedirectResponse(route('storefront.cart')));
        }

        if ($registration->requiresAccountForCheckout() && ! Auth::check()) {
            session()->put('url.intended', route('storefront.checkout'));
            throw new HttpResponseException(new RedirectResponse(route('login')));
        }

        $this->idempotency_key = (string) Str::uuid();
        $ids = app(AvailablePaymentMethods::class)->ids();

        /** @var Customer|null $customer */
        $customer = current_customer();
        if ($customer !== null) {
            $this->customer_name = $customer->name;
            $this->customer_email = $customer->email;

            $propertyAddress = $properties->addressFromProperties($customer);
            if ($propertyAddress !== null) {
                $this->fillBillingFromData($propertyAddress);
            } else {
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

            if (app(CustomerCreditLedger::class)->available($customer) > 0) {
                $this->payment_method = 'account_balance';
                $this->apply_credit = true;
            } else {
                $this->payment_method = $ids[0] ?? 'account_balance';
            }
        } else {
            $this->payment_method = $ids[0] ?? 'account_balance';
        }

        $this->propertyValues = $properties->emptyValues($properties->definitionsFor('checkout'), $customer);
    }

    public function updatedPaymentMethod(string $value): void
    {
        if ($value === 'account_balance') {
            $this->apply_credit = true;
        }
    }

    public function applySavedAddress(int $addressId): void
    {
        $customer = current_customer();
        if ($customer === null) {
            return;
        }

        $address = $customer->addresses()->whereKey($addressId)->first();
        if ($address === null) {
            return;
        }

        $this->fillBillingFromAddress($address);
    }

    public function updatedBillingLine1(string $value): void
    {
        $this->refreshAddressSuggestions(
            'billing',
            $value,
            $this->billing_country !== '' ? $this->billing_country : null,
            app(AddressAutocomplete::class),
            current_customer(),
        );
    }

    public function updatedShippingLine1(string $value): void
    {
        $this->refreshAddressSuggestions(
            'shipping',
            $value,
            $this->shipping_country !== '' ? $this->shipping_country : $this->billing_country,
            app(AddressAutocomplete::class),
            current_customer(),
        );
    }

    public function applyAddressSuggestion(int $index): void
    {
        $scope = $this->addressSuggestScope;
        $autocomplete = app(AddressAutocomplete::class);
        $result = $this->resolveSuggestion($index, $autocomplete);
        if ($result instanceof AddressSuggestion && $result->savedAddressId !== null) {
            if ($scope === 'shipping') {
                $this->applySavedAddressToShipping($result->savedAddressId);
            } else {
                $this->applySavedAddress($result->savedAddressId);
            }
            $this->clearAddressSuggestions();

            return;
        }

        if (! $result instanceof ResolvedAddress) {
            return;
        }

        if ($scope === 'shipping') {
            $this->fillShippingFromResolved($result);
        } else {
            $this->fillBillingFromResolved($result);
        }
    }

    public function continueStep(CartService $cart, CartRequirementComposer $composer, CustomerPropertyService $properties, CheckoutFlow $flow): void
    {
        $requirements = $composer->compose($cart);
        $current = $this->resolvedStep($flow, $requirements);
        $rules = $this->rulesForStep($current, $properties);
        if ($rules !== []) {
            $this->validate($rules, [], $this->validationAttributes());
        }
        $this->markCompleted($current);
        $next = $flow->next($requirements, $current);
        if ($next === null) {
            return;
        }
        $this->step = $next->value;
    }

    public function goToStep(string $step, CartService $cart, CartRequirementComposer $composer, CheckoutFlow $flow): void
    {
        $requirements = $composer->compose($cart);
        $target = $flow->resolve($requirements, $step);
        if (! $flow->canVisit($requirements, $target, $this->completedSteps) && $target !== CheckoutStep::Details) {
            return;
        }
        $this->step = $target->value;
    }

    public function back(CartService $cart, CartRequirementComposer $composer, CheckoutFlow $flow): void
    {
        $requirements = $composer->compose($cart);
        $previous = $flow->previous($requirements, $this->resolvedStep($flow, $requirements));
        if ($previous !== null) {
            $this->step = $previous->value;
        }
    }

    public function updatedBillingCountry(): void
    {
        $this->invalidateFrom(CheckoutStep::Delivery);
        $this->shipping_quote_key = '';
        $this->shipping_method_id = null;
    }

    public function updatedShippingCountry(): void
    {
        $this->invalidateFrom(CheckoutStep::Delivery);
        $this->shipping_quote_key = '';
        $this->shipping_method_id = null;
    }

    public function updatedShippingSameAsBilling(): void
    {
        $this->invalidateFrom(CheckoutStep::Delivery);
        $this->shipping_quote_key = '';
        $this->shipping_method_id = null;
    }

    public function placeOrder(
        PlaceOrder $placeOrder,
        CustomerRegistration $registration,
        CartService $cart,
        SaveCustomerAddress $saveAddress,
        CustomerPropertyService $properties,
        CartRequirementComposer $composer,
        StartOrderPayment $startPayment,
        CustomerCreditLedger $creditLedger,
    ): void {
        if ($registration->requiresAccountForCheckout() && ! Auth::check()) {
            session()->put('url.intended', route('storefront.checkout'));
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if ($this->shipping_quote_key === '' && $this->shipping_method_id !== null && $this->shipping_method_id > 0) {
            $this->shipping_quote_key = 'method:'.$this->shipping_method_id;
        }

        $allowed = app(AvailablePaymentMethods::class)->ids();
        $usingBalance = $this->payment_method === 'account_balance';
        if ($usingBalance) {
            $this->apply_credit = true;
        }

        $requirements = $composer->compose($cart);
        $requiresShipping = $requirements->requiresShipping();
        $checkoutProperties = $properties->nonAddressDefinitionsFor('checkout');
        $amountDue = $this->estimatedAmountDue($cart, $composer, $creditLedger);
        if ($usingBalance) {
            $paymentRules = ['required', 'string', Rule::in(['account_balance'])];
        } elseif ($amountDue > 0) {
            $paymentRules = ['required', 'string', Rule::in($allowed)];
        } else {
            $paymentRules = ['nullable', 'string'];
        }

        if ($amountDue > 0 && ! $usingBalance && $allowed === []) {
            $this->addError('payment_method', __('storefront.errors.payment_gateway_required'));

            return;
        }

        if ($usingBalance && $amountDue > 0) {
            $this->addError('payment_method', __('storefront.errors.payment_gateway_required'));

            return;
        }

        $rules = [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'payment_method' => $paymentRules,
            ...AddressValidation::rules('billing'),
            'save_billing_address' => ['boolean'],
            'apply_credit' => ['boolean'],
            ...$properties->livewireRules($checkoutProperties),
        ];
        if ($requiresShipping) {
            $rules['shipping_same_as_billing'] = ['boolean'];
            $rules['shipping_quote_key'] = ['required', 'string'];
            if (! $this->shipping_same_as_billing) {
                $rules = [...$rules, ...AddressValidation::rules('shipping')];
            }
        }

        $data = $this->validate($rules, [], $this->validationAttributes());

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
            'payment_method' => $amountDue > 0 ? ($data['payment_method'] ?? $this->payment_method) : ($usingBalance ? 'account_balance' : null),
            'customer_id' => current_customer()?->id,
            'billing' => $billing,
            'shipping' => $shipping,
            'shipping_same_as_billing' => $shippingSame,
            'shipping_method_id' => $requiresShipping ? $this->shipping_method_id : null,
            'shipping_quote_key' => $requiresShipping ? ($data['shipping_quote_key'] ?? $this->shipping_quote_key) : null,
            'discount_code' => $this->applied_coupon_code !== '' ? $this->applied_coupon_code : null,
            'apply_credit' => $usingBalance || (bool) ($data['apply_credit'] ?? false),
            'custom_properties' => $data['propertyValues'] ?? $this->propertyValues,
        ]);

        $order->loadMissing('payment');
        $access = app(StorefrontOrderAccess::class);
        $access->remember($order);
        $returnUrl = $access->paymentStatusUrl($order);
        if ($order->payment?->status !== PaymentStatus::Paid) {
            $attempt = $startPayment->handle(
                $order,
                (string) ($data['payment_method'] ?? $this->payment_method),
                $returnUrl,
                $returnUrl,
                'checkout-'.$order->id,
            );
            if ($attempt->status === PaymentAttemptStatus::Failed) {
                $this->addError('payment_method', __('storefront.errors.payment_unavailable'));

                return;
            }
            if (is_string($attempt->redirect_url) && $attempt->redirect_url !== '') {
                $this->redirect($attempt->redirect_url);

                return;
            }

            $this->redirect($returnUrl, navigate: true);

            return;
        }

        $this->redirect($access->confirmationUrl($order), navigate: true);
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
        TaxRateResolver $taxRates,
        SettingsRepository $settings,
        CustomerCreditLedger $creditLedger,
        CartRequirementComposer $composer,
        CustomerPropertyService $properties,
        CheckoutFlow $flow,
    ) {
        $theme = $themes->active();
        $lines = $cart->pricedLines();
        $subtotal = $cart->subtotal();
        $requirements = $composer->compose($cart);
        $requiresShipping = $requirements->requiresShipping();
        /** @var Customer|null $customer */
        $customer = current_customer();
        $creditBalance = $customer === null || $subtotal === null
            ? 0
            : $creditLedger->available($customer, $subtotal->currency);

        $quotes = [];
        $shippingTotal = null;
        $destination = $this->shippingDestination();
        if ($requiresShipping && $subtotal !== null) {
            $country = $destination->country !== '' ? $destination->country : 'NL';
            $quotes = $shippingQuotes->quotes(
                $cart->shippableLines(),
                $country,
                $subtotal->currency,
                $destination->isComplete() ? $destination : null,
            );
            $selected = null;
            foreach ($quotes as $quote) {
                if ($quote->key() === $this->shipping_quote_key) {
                    $selected = $quote;
                    break;
                }
            }
            if ($selected === null && $this->shipping_method_id !== null) {
                foreach ($quotes as $quote) {
                    if (! $quote->isCarrierQuote() && $quote->methodId === $this->shipping_method_id) {
                        $selected = $quote;
                        $this->shipping_quote_key = $quote->key();
                        break;
                    }
                }
            }
            if ($selected === null && $quotes !== []) {
                $selected = $quotes[0];
                $this->shipping_quote_key = $selected->key();
            }
            if ($selected !== null) {
                $shippingTotal = $selected->amount;
                $this->shipping_method_id = $selected->isCarrierQuote() ? null : $selected->methodId;
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
            $taxCountry = $country !== '' ? $country : 'NL';
            $tax = $taxes->calculate(
                $subtotalAfterDiscount,
                $shipping,
                $pricesIncludeTax,
                $taxRates->resolve($taxCountry),
            );
            $taxTotal = $tax->tax;
            $orderTotal = $subtotalAfterDiscount->add($shipping);
            if (! $pricesIncludeTax) {
                $orderTotal = $orderTotal->add($taxTotal);
            }
            if (($this->apply_credit || $this->payment_method === 'account_balance') && $creditBalance > 0) {
                $creditTotal = Money::of(min($creditBalance, $orderTotal->amount), $orderTotal->currency);
            }
        }

        $currentStep = $this->resolvedStep($flow, $requirements);
        if ($this->step !== $currentStep->value) {
            $this->step = $currentStep->value;
        }
        if (! $flow->canVisit($requirements, $currentStep, $this->completedSteps) && $currentStep !== CheckoutStep::Details) {
            $currentStep = $this->firstOpenStep($flow, $requirements);
            $this->step = $currentStep->value;
        }

        $nextStep = $flow->next($requirements, $currentStep);
        $amountDue = $orderTotal;
        if ($amountDue !== null && $creditTotal !== null) {
            $amountDue = $amountDue->subtract($creditTotal);
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
            'amountDue' => $amountDue,
            'theme' => $theme,
            'paymentOptions' => app(AvailablePaymentMethods::class)->options(),
            'developmentPayEnabled' => $this->developmentPayEnabled(),
            'customerLoggedIn' => Auth::check(),
            'registrationEnabled' => $registration->allowsRegistration(),
            'requiresShipping' => $requiresShipping,
            'requiresCustomProperties' => $requirements->has(CartRequirement::CustomProperties),
            'propertyDefinitions' => $properties->nonAddressDefinitionsFor('checkout'),
            'actor' => 'customer',
            'pricesIncludeTax' => $pricesIncludeTax,
            'creditBalance' => $creditBalance,
            'currentStep' => $currentStep,
            'progressItems' => $flow->progress($requirements, $currentStep, $this->completedSteps),
            'nextStep' => $nextStep,
            'countryOptions' => CheckoutCountries::options(),
            'primaryActionLabel' => $this->primaryActionLabel($currentStep, $nextStep, $amountDue),
            'savedAddresses' => $customer?->addresses()->orderByDesc('is_default_billing')->orderBy('id')->get() ?? collect(),
            'addressAutocompleteEnabled' => app(AddressAutocomplete::class)->enabled() || ($customer !== null && $customer->addresses()->exists()),
        ])->layout($theme->view('layouts.checkout'), [
            'title' => __('storefront.checkout.title'),
            'theme' => $theme,
        ]);
    }

    private function fillBillingFromAddress(CustomerAddress $address): void
    {
        $this->fillBillingFromData(AddressData::fromArray([
            'name' => $address->name,
            'company' => $address->company,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'region' => $address->region,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'phone' => $address->phone,
        ]));
    }

    private function fillBillingFromData(AddressData $address): void
    {
        $this->billing_name = $address->name;
        $this->billing_company = (string) ($address->company ?? '');
        $this->billing_line1 = $address->line1;
        $this->billing_line2 = (string) ($address->line2 ?? '');
        $this->billing_city = $address->city;
        $this->billing_region = (string) ($address->region ?? '');
        $this->billing_postal_code = $address->postalCode;
        $this->billing_country = $address->country;
        $this->billing_phone = (string) ($address->phone ?? '');
    }

    private function applySavedAddressToShipping(int $addressId): void
    {
        $customer = current_customer();
        if ($customer === null) {
            return;
        }

        $address = $customer->addresses()->whereKey($addressId)->first();
        if ($address === null) {
            return;
        }

        $this->shipping_same_as_billing = false;
        $this->shipping_name = $address->name;
        $this->shipping_company = (string) ($address->company ?? '');
        $this->shipping_line1 = $address->line1;
        $this->shipping_line2 = (string) ($address->line2 ?? '');
        $this->shipping_city = $address->city;
        $this->shipping_region = (string) ($address->region ?? '');
        $this->shipping_postal_code = $address->postal_code;
        $this->shipping_country = $address->country;
        $this->shipping_phone = (string) ($address->phone ?? '');
    }

    private function fillBillingFromResolved(ResolvedAddress $address): void
    {
        $this->billing_line1 = $address->line1;
        if ($address->line2 !== null && $address->line2 !== '') {
            $this->billing_line2 = $address->line2;
        }
        $this->billing_city = $address->city;
        $this->billing_region = (string) ($address->region ?? '');
        $this->billing_postal_code = $address->postalCode;
        $this->billing_country = $address->country;
    }

    private function fillShippingFromResolved(ResolvedAddress $address): void
    {
        $this->shipping_same_as_billing = false;
        $this->shipping_line1 = $address->line1;
        if ($address->line2 !== null && $address->line2 !== '') {
            $this->shipping_line2 = $address->line2;
        }
        $this->shipping_city = $address->city;
        $this->shipping_region = (string) ($address->region ?? '');
        $this->shipping_postal_code = $address->postalCode;
        $this->shipping_country = $address->country;
    }

    private function resolvedStep(CheckoutFlow $flow, CartRequirements $requirements): CheckoutStep
    {
        return $flow->resolve($requirements, $this->step);
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'customer_name' => __('storefront.checkout.name'),
            'customer_email' => __('storefront.checkout.email'),
            'billing_name' => __('storefront.checkout.address_name'),
            'billing_company' => __('storefront.checkout.company'),
            'billing_line1' => __('storefront.checkout.line1'),
            'billing_line2' => __('storefront.checkout.line2'),
            'billing_city' => __('storefront.checkout.city'),
            'billing_region' => __('storefront.checkout.region'),
            'billing_postal_code' => __('storefront.checkout.postal_code'),
            'billing_country' => __('storefront.checkout.country'),
            'billing_phone' => __('storefront.checkout.phone'),
            'shipping_name' => __('storefront.checkout.address_name'),
            'shipping_company' => __('storefront.checkout.company'),
            'shipping_line1' => __('storefront.checkout.line1'),
            'shipping_line2' => __('storefront.checkout.line2'),
            'shipping_city' => __('storefront.checkout.city'),
            'shipping_region' => __('storefront.checkout.region'),
            'shipping_postal_code' => __('storefront.checkout.postal_code'),
            'shipping_country' => __('storefront.checkout.country'),
            'shipping_phone' => __('storefront.checkout.phone'),
            'shipping_quote_key' => __('storefront.checkout.shipping_method'),
            'payment_method' => __('storefront.checkout.payment'),
            'discount_code' => __('storefront.checkout.discount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForStep(CheckoutStep $step, CustomerPropertyService $properties): array
    {
        $allowed = app(AvailablePaymentMethods::class)->ids();

        return match ($step) {
            CheckoutStep::Details => [
                'customer_name' => ['required', 'string', 'max:255'],
                'customer_email' => ['required', 'email', 'max:255'],
                ...AddressValidation::rules('billing'),
                ...$properties->livewireRules($properties->nonAddressDefinitionsFor('checkout')),
            ],
            CheckoutStep::Delivery, CheckoutStep::Fulfillment => [
                'shipping_same_as_billing' => ['boolean'],
                'shipping_quote_key' => ['required', 'string'],
                ...($this->shipping_same_as_billing ? [] : AddressValidation::rules('shipping')),
            ],
            CheckoutStep::Configuration => [],
            CheckoutStep::Payment => [
                'payment_method' => ['nullable', 'string'],
                'apply_credit' => ['boolean'],
            ],
            CheckoutStep::Review => [],
        };
    }

    private function firstOpenStep(CheckoutFlow $flow, CartRequirements $requirements): CheckoutStep
    {
        foreach ($flow->stepsFor($requirements) as $step) {
            if (! in_array($step->value, $this->completedSteps, true)) {
                return $step;
            }
        }

        return CheckoutStep::Details;
    }

    private function markCompleted(CheckoutStep $step): void
    {
        if (! in_array($step->value, $this->completedSteps, true)) {
            $this->completedSteps[] = $step->value;
        }
    }

    private function invalidateFrom(CheckoutStep $from): void
    {
        $drop = match ($from) {
            CheckoutStep::Delivery, CheckoutStep::Fulfillment, CheckoutStep::Configuration => [
                CheckoutStep::Delivery->value,
                CheckoutStep::Fulfillment->value,
                CheckoutStep::Configuration->value,
                CheckoutStep::Payment->value,
            ],
            default => [$from->value, CheckoutStep::Payment->value],
        };
        $this->completedSteps = array_values(array_filter(
            $this->completedSteps,
            static fn (string $step): bool => ! in_array($step, $drop, true),
        ));
    }

    private function shippingDestination(): ShippingDestination
    {
        if ($this->shipping_same_as_billing) {
            return new ShippingDestination(
                country: strtoupper($this->billing_country),
                postalCode: $this->billing_postal_code,
                city: $this->billing_city,
                line1: $this->billing_line1,
            );
        }

        return new ShippingDestination(
            country: strtoupper($this->shipping_country !== '' ? $this->shipping_country : $this->billing_country),
            postalCode: $this->shipping_postal_code !== '' ? $this->shipping_postal_code : $this->billing_postal_code,
            city: $this->shipping_city !== '' ? $this->shipping_city : $this->billing_city,
            line1: $this->shipping_line1 !== '' ? $this->shipping_line1 : $this->billing_line1,
        );
    }

    private function primaryActionLabel(CheckoutStep $current, ?CheckoutStep $next, ?Money $amountDue): string
    {
        if ($current === CheckoutStep::Payment || $next === null) {
            if ($amountDue !== null && $amountDue->amount < 1) {
                return __('storefront.checkout.place_order');
            }

            if ($this->payment_method === 'account_balance') {
                return __('storefront.checkout.pay_with_account_balance');
            }

            $gatewayId = CheckoutPaymentSelection::parse($this->payment_method)->gatewayId;
            if ($gatewayId === 'development' && $amountDue !== null) {
                return __('storefront.checkout.pay_amount', [
                    'amount' => MoneyFormatter::format($amountDue),
                ]);
            }

            $gateway = $gatewayId !== ''
                ? app(PaymentGatewayRegistry::class)->get($gatewayId)
                : null;
            if ($gateway !== null && $gateway->capabilities()->redirect) {
                return __('storefront.checkout.continue_to_provider', [
                    'provider' => __($gateway->label()),
                ]);
            }

            return __('storefront.checkout.place_order');
        }

        return __('storefront.checkout.continue_to', [
            'step' => __($next->labelKey()),
        ]);
    }

    private function estimatedAmountDue(
        CartService $cart,
        CartRequirementComposer $composer,
        CustomerCreditLedger $creditLedger,
    ): int {
        $subtotal = $cart->subtotal();
        if ($subtotal === null) {
            return 0;
        }

        $requirements = $composer->compose($cart);
        $shippingAmount = 0;
        if ($requirements->requiresShipping()) {
            // Match place-order totals loosely; exact shipping is validated later.
            $shippingAmount = 0;
        }

        $orderTotal = $subtotal->amount + $shippingAmount;
        $customer = current_customer();
        $applyCredit = $this->apply_credit || $this->payment_method === 'account_balance';
        if ($applyCredit && $customer !== null) {
            $credit = min(
                $creditLedger->available($customer, $subtotal->currency),
                $orderTotal,
            );
            $orderTotal -= $credit;
        }

        return max(0, $orderTotal);
    }

    private function developmentPayEnabled(): bool
    {
        return (bool) config('agovena.payments.allow_development_instant_pay')
            && ! app()->environment('production');
    }
}
