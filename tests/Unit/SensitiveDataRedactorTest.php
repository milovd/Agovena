<?php

declare(strict_types=1);

use App\Agovena\Security\SensitiveDataRedactor;

it('redacts sensitive associative and option values without redacting ciphertext', function (): void {
    $value = SensitiveDataRedactor::redact([
        'provider_settings' => [
            'location_id' => '1',
            'environment' => 'SECRET=do-not-store',
            'api_key' => 'do-not-store',
        ],
        'server_settings' => [
            'token_secret' => 'do-not-store',
        ],
        'provider_settings_encrypted' => 'opaque-ciphertext',
        'options' => [
            ['key' => 'environment', 'value' => 'SECRET=do-not-store', 'display' => 'SECRET=do-not-store'],
            ['key' => 'memory', 'value' => '1024', 'display' => '1024'],
        ],
    ]);

    expect($value)->toMatchArray([
        'provider_settings' => [
            'location_id' => '1',
            'environment' => '[REDACTED]',
            'api_key' => '[REDACTED]',
        ],
        'server_settings' => '[REDACTED]',
        'provider_settings_encrypted' => '[REDACTED]',
        'options' => [
            ['key' => 'environment', 'value' => '[REDACTED]', 'display' => '[REDACTED]'],
            ['key' => 'memory', 'value' => '1024', 'display' => '1024'],
        ],
    ]);
});

it('redacts embedded URL and connection-string credentials under ordinary keys', function (): void {
    $value = SensitiveDataRedactor::redact([
        'endpoint' => 'https://user:password@example.test/api?token=secret-token',
        'dsn' => 'postgres://user:password@example.test:5432/app',
        'label' => 'public endpoint',
    ]);

    expect($value)->toMatchArray([
        'endpoint' => '[REDACTED]',
        'dsn' => '[REDACTED]',
        'label' => 'public endpoint',
    ]);
});
