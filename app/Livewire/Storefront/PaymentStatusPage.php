<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Orders\StorefrontOrderAccess;
use App\Agovena\Payments\ReconcilePaymentStatus;
use App\Agovena\Theme\ThemeManager;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Livewire\Component;

/**
 * Return-URL landing page. Never treats the browser redirect as payment proof.
 */
final class PaymentStatusPage extends Component
{
    public Order $order;

    public function mount(Request $request, StorefrontOrderAccess $access, Order $order): void
    {
        $access->authorize($request, $order);
        $this->order = $order->load(['items', 'payment.attempts']);
    }

    public function refresh(ReconcilePaymentStatus $reconcile): void
    {
        $payment = $this->order->payment;
        if ($payment === null) {
            return;
        }

        $reconcile->handle($payment);
        $this->order = $this->order->fresh(['items', 'payment.attempts']) ?? $this->order;
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $payment = $this->order->payment;
        $attempt = $payment?->attempts()->latest('id')->first();

        return view($theme->view('checkout.payment-status'), [
            'order' => $this->order,
            'payment' => $payment,
            'attempt' => $attempt,
            'state' => $this->state($payment?->status, $attempt?->status),
            'shouldPoll' => $this->shouldPoll($payment?->status, $attempt?->status),
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('storefront.payment_status.page_title'),
            'theme' => $theme,
        ]);
    }

    private function state(?PaymentStatus $payment, ?PaymentAttemptStatus $attempt): string
    {
        if ($payment === PaymentStatus::Paid) {
            return 'paid';
        }
        if ($payment === PaymentStatus::Refunded || $payment === PaymentStatus::PartiallyRefunded) {
            return 'paid';
        }
        if ($attempt === PaymentAttemptStatus::Failed || $payment === PaymentStatus::Failed) {
            return 'failed';
        }
        if ($attempt === PaymentAttemptStatus::Cancelled || $payment === PaymentStatus::Cancelled) {
            return 'cancelled';
        }
        if ($attempt === PaymentAttemptStatus::Expired || $payment === PaymentStatus::Expired) {
            return 'expired';
        }
        if ($attempt === PaymentAttemptStatus::Processing || $attempt === PaymentAttemptStatus::Pending) {
            return 'pending';
        }

        return 'pending';
    }

    private function shouldPoll(?PaymentStatus $payment, ?PaymentAttemptStatus $attempt): bool
    {
        if ($payment === null) {
            return false;
        }

        return in_array($this->state($payment, $attempt), ['pending'], true);
    }
}
