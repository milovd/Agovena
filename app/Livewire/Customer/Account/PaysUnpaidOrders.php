<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Payments\AvailablePaymentMethods;
use App\Agovena\Payments\StartOrderPayment;
use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

trait PaysUnpaidOrders
{
    public string $pay_gateway = '';

    abstract protected function unpaidOrder(): ?Order;

    /**
     * @return list<array{id: string, label: string, gateway_id?: string, icon?: string|null}>
     */
    public function paymentGatewayOptions(): array
    {
        return app(AvailablePaymentMethods::class)->options();
    }

    public function payNow(StartOrderPayment $start): void
    {
        $order = $this->unpaidOrder();
        abort_unless($order !== null && $order->isAwaitingPayment(), 404);

        $options = $this->paymentGatewayOptions();
        $gatewayId = $this->pay_gateway !== ''
            ? $this->pay_gateway
            : (string) ($options[0]['id'] ?? 'manual');

        try {
            $attempt = $start->handle(
                $order,
                $gatewayId,
                route('storefront.payment.status', $order),
                route('storefront.payment.status', $order),
            );
        } catch (ValidationException $exception) {
            $this->addError('pay_gateway', collect($exception->errors())->flatten()->first() ?: $exception->getMessage());

            return;
        }

        if (is_string($attempt->redirect_url) && $attempt->redirect_url !== '') {
            $this->redirect($attempt->redirect_url);

            return;
        }

        $order = $order->fresh(['items', 'payment']) ?? $order;

        if ($attempt->status === PaymentAttemptStatus::Succeeded || ! $order->isAwaitingPayment()) {
            session()->flash('status', __('customer.account.payment_completed'));
        } elseif ($attempt->status === PaymentAttemptStatus::Failed) {
            session()->flash('status', __('customer.account.payment_failed'));
        } else {
            session()->flash('status', __('customer.account.payment_pending'));
        }

        $this->afterPaymentAttempt($order);
    }

    protected function afterPaymentAttempt(Order $order): void {}
}
