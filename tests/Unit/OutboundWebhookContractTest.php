<?php

declare(strict_types=1);

use App\Agovena\Webhooks\WebhookSigner;
use App\Agovena\Webhooks\WebhookUrlValidator;

it('signs webhook payloads with timestamped HMAC sha256', function (): void {
    $body = '{"id":"evt_test","type":"order.created"}';
    $timestamp = 1_700_000_000;
    $signature = WebhookSigner::sign('[REDACTED]', $timestamp, $body);

    expect($signature)->toBeString()
        ->and(WebhookSigner::verify('[REDACTED]', $timestamp, $body, $signature, $timestamp))->toBeTrue()
        ->and(WebhookSigner::verify('[REDACTED]', $timestamp, $body, $signature, $timestamp + 301))->toBeFalse();
});

it('rejects malformed or stale webhook signatures', function (): void {
    expect(WebhookSigner::verify('[REDACTED]', 1_700_000_000, '{}', 'v1=wrong', 1_700_000_000))->toBeFalse()
        ->and(WebhookSigner::verify('[REDACTED]', 1_700_000_000, '{}', '', 1_700_000_000))->toBeFalse()
        ->and(WebhookSigner::verify('[REDACTED]', 1_700_000_000, '{}', WebhookSigner::sign('[REDACTED]', 1_700_000_000, '{}'), 1_700_000_301))->toBeFalse();
});

it('allows public HTTPS webhook endpoints and rejects SSRF-shaped URLs', function (): void {
    expect(WebhookUrlValidator::isAllowed('https://hooks.example.test/agovena'))->toBeTrue()
        ->and(WebhookUrlValidator::isAllowed('http://hooks.example.test/agovena'))->toBeFalse()
        ->and(WebhookUrlValidator::isAllowed('https://localhost/hook'))->toBeFalse()
        ->and(WebhookUrlValidator::isAllowed('https://127.0.0.1/hook'))->toBeFalse()
        ->and(WebhookUrlValidator::isAllowed('https://10.0.0.10/hook'))->toBeFalse()
        ->and(WebhookUrlValidator::isAllowed('https://user:pass@example.test/hook'))->toBeFalse();
});
