<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use Closure;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

final class PaymentLifecycleLock
{
    public function run(int $orderId, Closure $callback): mixed
    {
        $this->assertSharedLockDriver();

        return Cache::lock($this->key($orderId), 120)->block(30, $callback);
    }

    public function key(int $orderId): string
    {
        return 'agovena:payment-lifecycle:order:'.$orderId;
    }

    private function assertSharedLockDriver(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $store = (string) config('cache.default');
        $driver = config('cache.stores.'.$store.'.driver');
        if (! is_string($driver) || ! in_array($driver, ['database', 'dynamodb', 'memcached', 'redis'], true)) {
            throw new RuntimeException('A shared cache lock driver is required for payment lifecycle operations.');
        }
    }
}
