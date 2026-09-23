<?php

declare(strict_types=1);

use App\Agovena\Payments\PaymentLifecycleLock;

function runOutsideTestingEnvironment(callable $callback): mixed
{
    $originalEnvironment = app()->environment();

    app()->detectEnvironment(static fn (): string => 'staging');

    try {
        return $callback();
    } finally {
        app()->detectEnvironment(static function () use ($originalEnvironment): string {
            return $originalEnvironment;
        });
    }
}

test('payment lifecycle lock uses the configured shared store outside testing', function (): void {
    config()->set('agovena.payments.lock_store', 'database');
    config()->set('cache.stores.database.driver', 'database');

    $result = runOutsideTestingEnvironment(
        static fn (): mixed => app(PaymentLifecycleLock::class)->run(901, static fn (): string => 'locked'),
    );

    expect($result)->toBe('locked');
});

test('payment lifecycle lock rejects a non-shared configured store outside testing', function (): void {
    config()->set('agovena.payments.lock_store', 'file');
    config()->set('cache.stores.file.driver', 'file');

    expect(fn (): mixed => runOutsideTestingEnvironment(
        static fn (): mixed => app(PaymentLifecycleLock::class)->run(902, static fn (): string => 'not-allowed'),
    ))
        ->toThrow(RuntimeException::class, 'AGOVENA_PAYMENT_LOCK_STORE');
});
