<?php

declare(strict_types=1);

namespace App\Agovena\Auth\OAuth;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Validation\ValidationException;

final class OAuthStateStore
{
    private const TTL_SECONDS = 600;

    /** @var list<string> */
    private const PROVIDERS = ['google', 'discord'];

    public function __construct(
        private readonly Repository $cache,
    ) {}

    public function issueWithNonce(string $provider, string $redirect): array
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw ValidationException::withMessages(['provider' => 'The OAuth provider is not supported.']);
        }

        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//') || preg_match('/[\x00-\x1F\x7F]/', $redirect) === 1) {
            throw ValidationException::withMessages(['redirect' => 'The OAuth redirect is not allowed.']);
        }

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $this->cache->put($this->key($provider, $state), [
            'redirect' => $redirect,
            'nonce' => $nonce,
        ], self::TTL_SECONDS);

        return ['state' => $state, 'nonce' => $nonce];
    }

    public function issue(string $provider, string $redirect): string
    {
        return $this->issueWithNonce($provider, $redirect)['state'];
    }

    /** @return array{redirect: string, nonce: string}|null */
    public function consumePayload(string $provider, string $state): ?array
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, self::PROVIDERS, true) || ! preg_match('/^[a-f0-9]{64}$/', $state)) {
            return null;
        }

        $payload = $this->cache->pull($this->key($provider, $state));

        return is_array($payload)
            && is_string($payload['redirect'] ?? null)
            && is_string($payload['nonce'] ?? null)
            ? ['redirect' => $payload['redirect'], 'nonce' => $payload['nonce']]
            : null;
    }

    public function consume(string $provider, string $state): ?string
    {
        return $this->consumePayload($provider, $state)['redirect'] ?? null;
    }

    private function key(string $provider, string $state): string
    {
        return 'agovena.oauth.state.'.hash('sha256', $provider.':'.$state);
    }
}
