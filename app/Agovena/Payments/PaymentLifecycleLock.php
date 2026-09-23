<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class PaymentLifecycleLock
{
    public function run(int $orderId, Closure $callback): mixed
    {
        $store = $this->storeName();
        $this->assertSharedLockDriver($store);
        $cacheStore = Cache::store($store)->getStore();
        if (! $cacheStore instanceof LockProvider) {
            throw new RuntimeException(sprintf('Configured payment lock store [%s] does not support shared locks.', $store));
        }

        return $cacheStore->lock($this->key($orderId), 120)->block(30, $callback);
    }

    public function key(int $orderId): string
    {
        return 'agovena:payment-lifecycle:order:'.$orderId;
    }

    private function storeName(): string
    {
        if (app()->environment('testing')) {
            return (string) config('cache.default');
        }

        return (string) config('agovena.payments.lock_store', 'database');
    }

    private function assertSharedLockDriver(string $store): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $driver = config('cache.stores.'.$store.'.driver');
        if (! is_string($driver) || ! in_array($driver, ['database', 'dynamodb', 'memcached', 'redis'], true)) {
            throw new RuntimeException(sprintf(
                'A shared cache lock driver is required for payment lifecycle operations. Store [%s] uses driver [%s]. Configure AGOVENA_PAYMENT_LOCK_STORE as database, redis, memcached, or dynamodb.',
                $store,
                is_string($driver) && $driver !== '' ? $driver : 'unknown',
            ));
        }
    }
}
