<?php

declare(strict_types=1);

namespace App\Agovena\Webhooks;

final class WebhookSigner
{
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function verify(
        string $secret,
        int $timestamp,
        string $body,
        string $signatureHeader,
        int $now,
        int $toleranceSeconds = 300,
    ): bool {
        if ($secret === '' || $signatureHeader === '' || abs($now - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = self::sign($secret, $timestamp, $body);
        foreach (preg_split('/[;,]/', $signatureHeader) ?: [] as $candidate) {
            if (hash_equals($expected, trim($candidate))) {
                return true;
            }
        }

        return false;
    }
}
