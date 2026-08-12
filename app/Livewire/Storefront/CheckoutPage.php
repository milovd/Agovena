<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Theme\ThemeManager;
use App\Enums\PaymentMethod;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class CheckoutPage extends Component
{
    public string $customer_name = '';

    public string $customer_email = '';

    public string $idempotency_key = '';

    public string $payment_method = 'manual';

    public function mount(CartService $cart): void
    {
        if ($cart->isEmpty()) {
            $this->redirect(route('storefront.cart'), navigate: true);
        }

        $this->idempotency_key = (string) Str::uuid();
        $this->payment_method = PaymentMethod::Manual->value;
    }

    public function placeOrder(PlaceOrder $placeOrder): void
    {
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
        ]);

        $this->redirect(route('storefront.order.confirmation', $order), navigate: true);
    }

    public function render(CartService $cart, ThemeManager $themes)
    {
        $theme = $themes->active();
        $lines = $cart->pricedLines();
        $subtotal = $cart->subtotal();

        return view($theme->view('checkout.index'), [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'theme' => $theme,
            'developmentPayEnabled' => $this->developmentPayEnabled(),
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
