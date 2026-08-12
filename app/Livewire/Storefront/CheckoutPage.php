<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\CustomerRegistration;
use App\Agovena\Theme\ThemeManager;
use App\Enums\PaymentMethod;
use App\Models\Customer;
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

    public function mount(CartService $cart, CustomerRegistration $registration): void
    {
        if ($cart->isEmpty()) {
            $this->redirect(route('storefront.cart'), navigate: true);

            return;
        }

        if ($registration->requiresAccountForCheckout() && ! Auth::guard('customer')->check()) {
            session()->put('url.intended', route('storefront.checkout'));
            $this->redirect(route('customer.login'), navigate: true);

            return;
        }

        $this->idempotency_key = (string) Str::uuid();
        $this->payment_method = PaymentMethod::Manual->value;

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer !== null) {
            $this->customer_name = $customer->name;
            $this->customer_email = $customer->email;
        }
    }

    public function placeOrder(PlaceOrder $placeOrder, CustomerRegistration $registration): void
    {
        if ($registration->requiresAccountForCheckout() && ! Auth::guard('customer')->check()) {
            session()->put('url.intended', route('storefront.checkout'));
            $this->redirect(route('customer.login'), navigate: true);

            return;
        }

        $allowed = [PaymentMethod::Manual->value];
        if ($this->developmentPayEnabled()) {
            $allowed[] = PaymentMethod::Development->value;
        }

        $data = $this->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'payment_method' => ['required', 'string', Rule::in($allowed)],
        ]);

        $order = $placeOrder->handle([
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'idempotency_key' => $data['idempotency_key'],
            'payment_method' => $data['payment_method'],
            'customer_id' => Auth::guard('customer')->id(),
        ]);

        $this->redirect(route('storefront.order.confirmation', $order), navigate: true);
    }

    public function render(CartService $cart, ThemeManager $themes, CustomerRegistration $registration)
    {
        $theme = $themes->active();
        $lines = $cart->pricedLines();
        $subtotal = $cart->subtotal();

        return view($theme->view('checkout.index'), [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'theme' => $theme,
            'developmentPayEnabled' => $this->developmentPayEnabled(),
            'customerLoggedIn' => Auth::guard('customer')->check(),
            'registrationEnabled' => $registration->allowsRegistration(),
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('storefront.checkout.title'),
            'theme' => $theme,
        ]);
    }

    private function developmentPayEnabled(): bool
    {
        return (bool) config('agovena.payments.allow_development_instant_pay')
            && ! app()->environment('production');
    }
}
