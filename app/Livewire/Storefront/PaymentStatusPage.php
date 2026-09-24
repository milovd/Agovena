<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Agovena\Orders\StorefrontOrderAccess;
use App\Agovena\Payments\ReconcilePaymentStatus;
use App\Agovena\Theme\ThemeManager;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Livewire\Component;

/**
 * Return-URL landing page. Never treats the browser redirect as payment proof.
 * When paid, continue into checkout Finish (order confirmation chrome).
 */
final class PaymentStatusPage extends Component
{
    public Order $order;

    public ?string $checkoutEvent = null;

    public function mount(Request $request, StorefrontOrderAccess $access, Order $order, ReconcilePaymentStatus $reconcile): void
    {
        $access->authorize($request, $order);
        $event = $request->query('checkout_event');
        $this->checkoutEvent = is_string($event) && in_array($event, ['failed', 'cancelled', 'error'], true)
            ? $event
            : null;
        $this->order = $order->load(['items', 'payment.attempts']);

        $payment = $this->order->payment;
        if ($payment !== null) {
            $reconcile->handle($payment);
            $this->order = $this->order->fresh(['items', 'payment.attempts']) ?? $this->order;
        }

        if ($this->order->payment?->status === PaymentStatus::Paid) {
            throw new HttpResponseException(new RedirectResponse(
                $access->confirmationUrl($this->order),
            ));
        }
    }

    public function refresh(ReconcilePaymentStatus $reconcile, StorefrontOrderAccess $access): void
    {
        $payment = $this->order->payment;
        if ($payment === null) {
            return;
        }

        $reconcile->handle($payment);
        $this->order = $this->order->fresh(['items', 'payment.attempts']) ?? $this->order;

        if ($this->order->payment?->status === PaymentStatus::Paid) {
            $this->redirect($access->confirmationUrl($this->order), navigate: true);
        }
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
            'checkoutEvent' => $this->checkoutEvent,
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
        if ($this->checkoutEvent === 'failed' || $this->checkoutEvent === 'error') {
            return 'failed';
        }
        if ($this->checkoutEvent === 'cancelled') {
            return 'cancelled';
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

        if ($this->state($payment, $attempt) === 'pending') {
            return true;
        }

        return in_array($this->checkoutEvent, ['failed', 'error'], true)
            && $payment === PaymentStatus::Pending
            && in_array($attempt, [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing], true);
    }
}
