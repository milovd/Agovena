<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Extensions\ExtensionSettingsRepository;
use Illuminate\Contracts\Cache\Repository;

final class PaymentMethodDiscoveryCache
{
    public function __construct(
        private readonly Repository $cache,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $form
     */
    public function fingerprint(
        string $extensionId,
        array $definitions,
        array $form,
        ExtensionSettingsRepository $settings,
    ): string {
        $values = [];
        foreach ($definitions as $definition) {
            if ((($definition['type'] ?? 'string') === 'payment_methods')
                || (! ($definition['connection'] ?? false) && ! ($definition['connection_context'] ?? false))) {
                continue;
            }

            $key = (string) ($definition['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $value = $form[$key] ?? null;
            if (($definition['secret'] ?? false) && (! is_string($value) || trim($value) === '')) {
                $value = $settings->get($extensionId, $key);
            }

            $values[$key] = $this->normalize($value);
        }

        return $this->fingerprintValues($extensionId, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function fingerprintValues(string $extensionId, array $values): string
    {
        ksort($values);
        $payload = json_encode([
            'extension' => $extensionId,
            'settings' => $this->normalize($values),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payload, (string) config('app.key', 'agovena'));
    }

    /**
     * @return list<array{id: string, label: string, icon: ?string}>|null
     */
    public function get(string $extensionId, string $fingerprint, bool $requireVerifiedConnection = true): ?array
    {
        $value = $this->cache->get($this->key($extensionId, $fingerprint));
        if (! is_array($value) || ! array_key_exists('methods', $value) || ! is_array($value['methods'])) {
            return null;
        }

        $storedAt = $value['stored_at'] ?? null;
        $storedTimestamp = is_string($storedAt) ? strtotime($storedAt) : false;
        $ttl = max(60, (int) config('agovena.payments.payment_method_discovery_ttl', 3600));
        if ($storedTimestamp === false || max(0, now()->timestamp - $storedTimestamp) >= $ttl) {
            return null;
        }

        if ($requireVerifiedConnection && ($value['connection_verified'] ?? false) !== true) {
            return null;
        }

        $methods = [];
        foreach ($value['methods'] as $method) {
            if (! is_array($method)
                || ! is_string($method['id'] ?? null)
                || $method['id'] === ''
                || ! is_string($method['label'] ?? null)
                || $method['label'] === ''
                || ($method['icon'] ?? null) !== null && ! is_string($method['icon'])) {
                return null;
            }

            $methods[] = [
                'id' => $method['id'],
                'label' => $method['label'],
                'icon' => $method['icon'] ?? null,
            ];
        }

        return $methods;
    }

    /**
     * @param  list<array{id: string, label: string, icon: ?string}>  $methods
     */
    public function put(string $extensionId, string $fingerprint, array $methods, bool $connectionVerified = true): void
    {
        $this->cache->put($this->key($extensionId, $fingerprint), [
            'methods' => $methods,
            'connection_verified' => $connectionVerified,
            'stored_at' => now()->toIso8601String(),
        ], max(60, (int) config('agovena.payments.payment_method_discovery_ttl', 3600)));
    }

    public function forget(string $extensionId, string $fingerprint): void
    {
        $this->cache->forget($this->key($extensionId, $fingerprint));
    }

    private function key(string $extensionId, string $fingerprint): string
    {
        $safeExtensionId = preg_replace('/[^A-Za-z0-9_.-]/', '_', $extensionId) ?? 'extension';

        return 'agovena.payment-method-discovery.'.$safeExtensionId.'.'.$fingerprint;
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[(string) $key] = $this->normalize($item);
            }

            ksort($normalized);

            return $normalized;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }
}
