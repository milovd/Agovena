<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

use App\Agovena\Payments\ProviderSubscriptionEvent;
use App\Agovena\Payments\WebhookPayload;

/**
 * Optional capability for providers that own recurring subscription billing.
 *
 * Unlike ChargesRecurringPayments, the provider creates and renews the
 * subscription itself after the initial checkout.
 */
interface ManagesProviderSubscriptions
{
    public function managesProviderSubscriptions(): bool;

    public function cancelProviderSubscription(string $externalId, bool $atPeriodEnd): void;

    public function resumeProviderSubscription(string $externalId): void;

    public function providerSubscriptionEvent(WebhookPayload $payload): ?ProviderSubscriptionEvent;
}
