<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Gateways;

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
 * Core reference adapter for offline/manual payment recording.
 * Registered by the manual-payment Extension when enabled.
 */
final class ManualPaymentGateway implements PaymentGateway
{
    public function id(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'storefront.checkout.payment_manual';
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
        return PaymentInitiationResult::pending(
            message: __('storefront.checkout.manual_pending'),
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
            externalRefundId: 'manual-refund-'.$request->payment->id,
            metadata: ['mode' => 'manual'],
        );
    }

    public function health(): HealthResult
    {
        return HealthResult::ok(__('admin.extensions.health.manual_ok'));
    }
}
