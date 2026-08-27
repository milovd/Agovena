<?php

declare(strict_types=1);

use App\Agovena\Auth\OAuth\OAuthStateStore;
use Illuminate\Validation\ValidationException;

it('issues and consumes a single-use oauth state with a safe internal redirect', function (): void {
    $store = app(OAuthStateStore::class);

    $state = $store->issue('google', '/account/security', 'browser-session-a');
    $redirect = $store->consume('google', $state, 'browser-session-a');

    expect($state)->toHaveLength(64)
        ->and($redirect)->toBe('/account/security')
        ->and($store->consume('google', $state, 'browser-session-a'))->toBeNull();
});

it('rejects oauth state from another browser session', function (): void {
    $store = app(OAuthStateStore::class);

    $state = $store->issue('google', '/account/security', 'browser-session-a');

    expect($store->consume('google', $state, 'browser-session-b'))->toBeNull();
});

it('rejects external oauth redirect targets and unsupported providers', function (): void {
    $store = app(OAuthStateStore::class);

    expect(fn () => $store->issue('unknown', '/account', 'browser-session'))->toThrow(ValidationException::class)
        ->and(fn () => $store->issue('discord', 'https://evil.example.test/callback', 'browser-session'))->toThrow(ValidationException::class);
});
