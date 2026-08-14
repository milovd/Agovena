<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Invoices\AssertInvoiceCanBePaid;
use App\Agovena\Orders\StorefrontOrderAccess;
use App\Agovena\Payments\Contracts\ChargesRecurringPayments;
use App\Agovena\Payments\Contracts\OffersReusablePaymentAuthorization;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Off-session recurring charge through a PaymentGateway contract.
 * Subscriptions and other Modules must not call provider APIs directly.
 */
final class ChargeRecurringPayment
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly ApplyNormalizedPaymentStatus $applyStatus,
        private readonly ReconcilePaymentStatus $reconcile,
        private readonly AssertInvoiceCanBePaid $assertInvoiceCanBePaid,
        private readonly ResolvesReusablePaymentAuthorizations $authorizations,
    ) {}

    public function handle(Order $order, ?string $idempotencyKey = null): RecurringChargeResult
    {
        $order->loadMissing('payment', 'invoice');
        $payment = $order->payment;

        if ($payment === null) {
            return RecurringChargeResult::skipped('payment_unavailable');
        }

        if ($payment->status === PaymentStatus::Paid) {
            return RecurringChargeResult::charged();
        }

        if (! $order->isAwaitingPayment()) {
            return RecurringChargeResult::skipped('not_awaiting_payment');
        }

        try {
            $this->assertInvoiceCanBePaid->handle($order);
        } catch (Throwable) {
            return RecurringChargeResult::skipped('invoice_not_payable');
        }

        $gatewayId = CheckoutPaymentSelection::parse((string) $payment->method)->gatewayId;
        if ($gatewayId === '') {
            return RecurringChargeResult::skipped('no_gateway');
        }

        $gateway = $this->resolveGateway($gatewayId);
        if ($gateway === null) {
            return RecurringChargeResult::skipped('gateway_unavailable');
        }

        if (! $gateway->capabilities()->recurring || ! $gateway instanceof ChargesRecurringPayments) {
            return RecurringChargeResult::skipped('recurring_unsupported');
        }

        $authorization = $this->authorizations->forCustomer(
            $gatewayId,
            $order->customer_id !== null ? (int) $order->customer_id : null,
            (string) $order->customer_email,
        );
        if ($gateway instanceof OffersReusablePaymentAuthorization && ! $authorization->available) {
            return RecurringChargeResult::skipped('authorization_missing', authorizationMissing: true);
        }

        $open = $this->openRecurringAttempt($payment, $gatewayId);
        if ($open !== null) {
            return $this->resumeOpenAttempt($order, $payment, $gateway, $open);
        }

        $key = $idempotencyKey !== null && $idempotencyKey !== ''
            ? $idempotencyKey
            : $this->nextIdempotencyKey($payment, $gatewayId);

        $existing = PaymentAttempt::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $this->resumeOpenAttempt($order, $payment, $gateway, $existing);
        }

        return $this->charge($order, $payment, $gateway, $key);
    }

    private function resumeOpenAttempt(
        Order $order,
        Payment $payment,
        PaymentGateway $gateway,
        PaymentAttempt $attempt,
    ): RecurringChargeResult {
        if ($attempt->status === PaymentAttemptStatus::Succeeded || $payment->status === PaymentStatus::Paid) {
            return RecurringChargeResult::charged($attempt);
        }

        if ($attempt->external_id !== null && $attempt->external_id !== '') {
            $this->reconcile->handle($payment->fresh() ?? $payment);
            $payment = $payment->fresh() ?? $payment;
            $attempt = $attempt->fresh() ?? $attempt;

            if ($payment->status === PaymentStatus::Paid || $attempt->status === PaymentAttemptStatus::Succeeded) {
                return RecurringChargeResult::charged($attempt);
            }

            if (in_array($attempt->status, [PaymentAttemptStatus::Failed, PaymentAttemptStatus::Cancelled, PaymentAttemptStatus::Expired], true)) {
                return RecurringChargeResult::failed($attempt, $this->attemptError($attempt));
            }

            return RecurringChargeResult::pending($attempt);
        }

        if ($gateway instanceof ChargesRecurringPayments
            && in_array($attempt->status, [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing], true)) {
            return $this->chargeExisting($order, $payment, $gateway, $attempt);
        }

        if (in_array($attempt->status, [PaymentAttemptStatus::Failed, PaymentAttemptStatus::Cancelled, PaymentAttemptStatus::Expired], true)) {
            return RecurringChargeResult::failed($attempt, $this->attemptError($attempt));
        }

        return RecurringChargeResult::pending($attempt);
    }

    private function charge(Order $order, Payment $payment, ChargesRecurringPayments&PaymentGateway $gateway, string $key): RecurringChargeResult
    {
        $attempt = DB::transaction(function () use ($payment, $gateway, $key): PaymentAttempt {
            $created = PaymentAttempt::query()->create([
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'gateway_id' => $gateway->id(),
                'status' => PaymentAttemptStatus::Processing,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'idempotency_key' => $key,
                'request_meta' => ['purpose' => 'recurring'],
                'initiated_at' => now(),
            ]);

            return $created;
        });

        return $this->chargeExisting($order, $payment, $gateway, $attempt);
    }

    private function chargeExisting(
        Order $order,
        Payment $payment,
        ChargesRecurringPayments&PaymentGateway $gateway,
        PaymentAttempt $attempt,
    ): RecurringChargeResult {
        $returnUrl = Route::has('storefront.payment.status')
            ? app(StorefrontOrderAccess::class)->paymentStatusUrl($order)
            : url('/');

        try {
            $result = $gateway->charge(new PaymentInitiation(
                order: $order,
                payment: $payment,
                returnUrl: $returnUrl,
                cancelUrl: $returnUrl,
                metadata: ['purpose' => 'recurring'],
                idempotencyKey: (string) $attempt->idempotency_key,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return RecurringChargeResult::pending($attempt);
        }

        $attempt->external_id = $result->externalId;
        $attempt->redirect_url = $result->redirectUrl;
        $attempt->response_meta = $this->redact($result->metadata);
        if ($result->message !== null) {
            $attempt->response_meta = array_merge($attempt->response_meta ?? [], [
                'message' => $result->message,
            ]);
        }

        if ($result->status === 'completed') {
            $attempt->save();
            $this->applyStatus->handle($attempt->fresh() ?? $attempt, PaymentStatus::Paid);

            return RecurringChargeResult::charged($attempt->fresh() ?? $attempt);
        }

        if ($result->status === 'failed') {
            $missing = ($result->metadata['reason'] ?? null) === 'authorization_missing';
            if ($missing) {
                $attempt->status = PaymentAttemptStatus::Failed;
                $attempt->completed_at = now();
                $attempt->save();

                return RecurringChargeResult::failed(
                    $attempt,
                    $result->message,
                    authorizationMissing: true,
                );
            }

            if ($result->externalId === null || $result->externalId === '') {
                $attempt->status = PaymentAttemptStatus::Processing;
                $attempt->save();

                return RecurringChargeResult::failed($attempt, $result->message);
            }

            $attempt->status = PaymentAttemptStatus::Failed;
            $attempt->completed_at = now();
            $attempt->save();

            return RecurringChargeResult::failed($attempt, $result->message);
        }

        if ($result->status === 'redirect') {
            $attempt->status = PaymentAttemptStatus::Failed;
            $attempt->completed_at = now();
            $attempt->response_meta = array_merge($attempt->response_meta ?? [], [
                'error' => 'off_session_redirect',
            ]);
            $attempt->save();

            return RecurringChargeResult::skipped('off_session_redirect', authorizationMissing: true);
        }

        $attempt->status = PaymentAttemptStatus::Processing;
        $attempt->save();

        return RecurringChargeResult::pending($attempt->fresh() ?? $attempt);
    }

    private function openRecurringAttempt(Payment $payment, string $gatewayId): ?PaymentAttempt
    {
        return PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->where('gateway_id', $gatewayId)
            ->whereIn('status', [PaymentAttemptStatus::Pending, PaymentAttemptStatus::Processing])
            ->latest('id')
            ->first();
    }

    private function nextIdempotencyKey(Payment $payment, string $gatewayId): string
    {
        $index = PaymentAttempt::query()
            ->where('payment_id', $payment->id)
            ->where('gateway_id', $gatewayId)
            ->count();

        return 'recurring-'.$payment->id.'-'.$index;
    }

    private function resolveGateway(string $gatewayId): ?PaymentGateway
    {
        $gateway = $this->gateways->get($gatewayId);
        if ($gateway !== null) {
            return $gateway;
        }

        return match ($gatewayId) {
            'manual' => app(ManualPaymentGateway::class),
            'development' => (bool) config('agovena.payments.allow_development_instant_pay')
                ? app(DevelopmentPaymentGateway::class)
                : null,
            default => null,
        };
    }

    private function attemptError(PaymentAttempt $attempt): ?string
    {
        $meta = $attempt->response_meta ?? [];
        $message = $meta['message'] ?? $meta['error'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function redact(array $meta): array
    {
        $out = [];
        foreach ($meta as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'secret') || str_contains($lower, 'token') || str_contains($lower, 'password') || str_contains($lower, 'key')) {
                $out[$key] = '[redacted]';

                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }
}
