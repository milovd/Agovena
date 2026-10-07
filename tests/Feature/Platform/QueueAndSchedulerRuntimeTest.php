<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Models\EmailLog;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('schedule run records a native heartbeat', function () {
    Cache::forget('agovena:scheduler:heartbeat');

    $this->artisan('schedule:run')->assertSuccessful();

    expect(Cache::get('agovena:scheduler:heartbeat'))->toBeString()->not->toBe('');
});

test('database queue worker consumes a dispatched job once', function () {
    config(['queue.default' => 'database']);
    Cache::put('agovena-queue-proof', 0);

    dispatch(function (): void {
        Cache::increment('agovena-queue-proof');
    });

    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', [
        '--once' => true,
        '--tries' => 1,
    ])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and((int) Cache::get('agovena-queue-proof'))->toBe(1);
});

test('commerce order placed notification is delivered by the database queue worker', function () {
    config(['queue.default' => 'database', 'mail.default' => 'array']);

    $product = Product::factory()->active()->create(['price_amount' => 1500]);
    app(CartService::class)->add($product->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Queue Buyer',
        'customer_email' => 'queue-buyer@example.test',
        'billing' => AddressData::fromArray([
            'name' => 'Queue Buyer',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    expect(DB::table('jobs')->count())->toBeGreaterThan(0);

    $remaining = (int) DB::table('jobs')->count();
    for ($i = 0; $i < $remaining + 2; $i++) {
        if (DB::table('jobs')->count() === 0) {
            break;
        }
        $this->artisan('queue:work', [
            '--once' => true,
            '--tries' => 1,
        ])->assertSuccessful();
    }

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(EmailLog::query()->where('to', 'queue-buyer@example.test')->where('status', 'sent')->exists())->toBeTrue()
        ->and($order->fresh())->not->toBeNull();
});
