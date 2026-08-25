<?php

declare(strict_types=1);

use Agovena\Extensions\Paddle\PaddleStatusMapper;
use Agovena\Extensions\Paddle\PaddleWebhookVerifier;
use Agovena\Extensions\Tebex\TebexStatusMapper;
use Agovena\Extensions\Tebex\TebexWebhookVerifier;
use App\Agovena\Extensions\ExtensionManager;
use App\Enums\PaymentStatus;

beforeEach(function (): void {
    app(ExtensionManager::class)->discover();
});

it('maps Paddle transaction and adjustment statuses conservatively', function (): void {
    expect(PaddleStatusMapper::map('completed'))->toBe(PaymentStatus::Paid)
        ->and(PaddleStatusMapper::map('past_due'))->toBe(PaymentStatus::Failed)
        ->and(PaddleStatusMapper::map('canceled'))->toBe(PaymentStatus::Cancelled)
        ->and(PaddleStatusMapper::map('pending_approval'))->toBe(PaymentStatus::Pending)
        ->and(PaddleStatusMapper::map('approved', 'refund'))->toBe(PaymentStatus::Refunded);
});

it('maps Tebex webhook statuses', function (): void {
    expect(TebexStatusMapper::fromWebhook('payment.completed'))->toBe(PaymentStatus::Paid)
        ->and(TebexStatusMapper::fromWebhook('payment.refunded'))->toBe(PaymentStatus::Refunded)
        ->and(TebexStatusMapper::fromWebhook('payment.declined'))->toBe(PaymentStatus::Failed)
        ->and(TebexStatusMapper::fromWebhook('recurring-payment.ended'))->toBe(PaymentStatus::Cancelled)
        ->and(TebexStatusMapper::fromWebhook('validation.webhook'))->toBe(PaymentStatus::Pending);
});

it('verifies Paddle signatures against the raw body and timestamp', function (): void {
    $body = '{"event_type":"transaction.paid"}';
    $timestamp = (string) time();
    $secret = '[REDACTED]';
    $signature = hash_hmac('sha256', $timestamp.':'.$body, $secret);

    expect(PaddleWebhookVerifier::verify($body, "ts={$timestamp};h1={$signature}", $secret))->toBeTrue()
        ->and(PaddleWebhookVerifier::verify($body.'x', "ts={$timestamp};h1={$signature}", $secret))->toBeFalse()
        ->and(PaddleWebhookVerifier::verify($body, 'ts='.((int) $timestamp - 6).";h1={$signature}", $secret))->toBeFalse();
});

it('verifies Tebex signatures over the hashed raw body', function (): void {
    $body = '{"type":"payment.completed"}';
    $secret = '[REDACTED]';
    $signature = hash_hmac('sha256', hash('sha256', $body), $secret);

    expect(TebexWebhookVerifier::verify($body, $signature, $secret))->toBeTrue()
        ->and(TebexWebhookVerifier::verify($body.'x', $signature, $secret))->toBeFalse();
});
