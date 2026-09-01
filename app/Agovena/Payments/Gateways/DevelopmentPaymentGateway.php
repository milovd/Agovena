<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Gateways;

use App\Agovena\Payments\CompleteDevelopmentPayment;
use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\HealthResult;
use App\Agovena\Payments\PaymentGatewayCapabilities;
use App\Agovena\Payments\PaymentInitiation;
use App\Agovena\Payments\PaymentInitiationResult;
use App\Agovena\Payments\RefundRequest;
use App\Agovena\Payments\RefundResult;
use App\Agovena\Payments\WebhookPayload;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;

/**
 * Local/dev instant-pay adapter. Never a production gateway.
 */
final class DevelopmentPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly CompleteDevelopmentPayment $complete,
    ) {}

    public function id(): string
    {
        return 'development';
    }

    public function label(): string
    {
        return 'storefront.checkout.payment_development';
    }

    public function capabilities(): PaymentGatewayCapabilities
    {
        return new PaymentGatewayCapabilities(
            refunds: true,
            partialRefunds: true,
            recurring: false,
            webhooks: false,
            redirect: false,
        );
    }

    public function initiate(PaymentInitiation $request): PaymentInitiationResult
    {
        if (! (bool) config('agovena.payments.allow_development_instant_pay')) {
            return PaymentInitiationResult::failed(__('storefront.checkout.development_disabled'));
        }

        $this->complete->handle($request->order->fresh(['payment']) ?? $request->order, lifecycleLockHeld: true);

        return PaymentInitiationResult::completed(
            externalId: 'dev-'.$request->payment->id,
            metadata: ['mode' => 'development'],
        );
    }

    public function mapStatus(string $providerStatus): PaymentStatus
    {
        return PaymentStatus::tryFrom($providerStatus) ?? PaymentStatus::Pending;
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): WebhookPayload
    {
        return new WebhookPayload(
            externalEventId: null,
            externalPaymentId: null,
            status: PaymentStatus::Pending,
            raw: [],
        );
    }

    public function refund(RefundRequest $request): RefundResult
    {
        return RefundResult::ok(
            externalRefundId: 'dev-refund-'.$request->payment->id,
            metadata: ['mode' => 'development'],
        );
    }

    public function health(): HealthResult
    {
        if (! (bool) config('agovena.payments.allow_development_instant_pay')) {
            return HealthResult::fail(__('storefront.checkout.development_disabled'));
        }

        return HealthResult::ok(__('admin.extensions.health.development_ok'));
    }
}
