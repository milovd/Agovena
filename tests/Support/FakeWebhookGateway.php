<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Agovena\Payments\Contracts\PaymentGateway;
use App\Agovena\Payments\Contracts\ValidatesWebhookPayload;
use App\Agovena\Payments\HealthResult;
use App\Agovena\Payments\PaymentGatewayCapabilities;
use App\Agovena\Payments\PaymentInitiation;
use App\Agovena\Payments\PaymentInitiationResult;
use App\Agovena\Payments\RefundRequest;
use App\Agovena\Payments\RefundResult;
use App\Agovena\Payments\WebhookPayload;
use App\Enums\PaymentStatus;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;

/**
 * Test-only webhook-capable gateway for ingress/idempotency coverage.
 */
final class FakeWebhookGateway implements PaymentGateway, ValidatesWebhookPayload
{
    public function __construct(
        private readonly string $secret = 'test-secret',
        private readonly bool $refunds = false,
    ) {}

    public function id(): string
    {
        return 'fake-webhook';
    }

    public function label(): string
    {
        return 'Fake webhook gateway';
    }

    public function capabilities(): PaymentGatewayCapabilities
    {
        return new PaymentGatewayCapabilities(
            refunds: $this->refunds,
            partialRefunds: false,
            recurring: false,
            webhooks: true,
            redirect: true,
        );
    }

    public function initiate(PaymentInitiation $request): PaymentInitiationResult
    {
        return PaymentInitiationResult::redirect(
            url: 'https://example.test/pay/'.$request->payment->id,
            externalId: 'ext-pay-'.$request->payment->id,
        );
    }

    public function mapStatus(string $providerStatus): PaymentStatus
    {
        return PaymentStatus::tryFrom($providerStatus) ?? PaymentStatus::Pending;
    }

    public function verifyWebhook(Request $request): bool
    {
        return hash_equals($this->secret, (string) $request->header('X-Webhook-Secret', ''));
    }

    public function parseWebhook(Request $request): WebhookPayload
    {
        /** @var array<string, mixed> $raw */
        $raw = $request->all();

        return new WebhookPayload(
            externalEventId: isset($raw['event_id']) ? (string) $raw['event_id'] : null,
            externalPaymentId: isset($raw['payment_id']) ? (string) $raw['payment_id'] : null,
            status: $this->mapStatus((string) ($raw['status'] ?? 'pending')),
            raw: $raw,
        );
    }

    public function validateWebhookPayload(PaymentAttempt $attempt, WebhookPayload $payload): bool
    {
        return $attempt->external_id === $payload->externalPaymentId;
    }

    public function refund(RefundRequest $request): RefundResult
    {
        if (! $this->refunds) {
            return RefundResult::fail(__('admin.payments.refunds_not_supported'));
        }

        return RefundResult::ok('refund-'.$request->payment->id);
    }

    public function health(): HealthResult
    {
        return HealthResult::ok('fake ok');
    }
}
