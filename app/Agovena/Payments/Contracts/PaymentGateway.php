<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

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
 * Agovena-owned payment provider seam. Implementations live in Extensions (or Core reference adapters).
 * Do not import vendor SDKs into Core.
 */
interface PaymentGateway
{
    public function id(): string;

    /** Translation key or plain label for checkout/Admin. */
    public function label(): string;

    public function capabilities(): PaymentGatewayCapabilities;

    public function initiate(PaymentInitiation $request): PaymentInitiationResult;

    public function mapStatus(string $providerStatus): PaymentStatus;

    public function verifyWebhook(Request $request): bool;

    public function parseWebhook(Request $request): WebhookPayload;

    public function refund(RefundRequest $request): RefundResult;

    public function health(): HealthResult;
}
