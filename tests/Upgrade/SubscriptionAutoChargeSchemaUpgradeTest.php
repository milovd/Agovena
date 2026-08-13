<?php

declare(strict_types=1);

use App\Agovena\Modules\ModuleManager;
use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('subscription auto-charge columns can be applied onto existing subscription tables', function () {
    Artisan::call('migrate');
    app(ModuleManager::class)->enable('subscriptions');

    expect(Schema::hasColumn('subscriptions', 'payment_gateway'))->toBeTrue()
        ->and(Schema::hasColumn('subscription_renewals', 'charge_attempts'))->toBeTrue();

    $order = Order::factory()->create([
        'customer_email' => 'upgrade-sub@example.test',
        'status' => 'paid',
        'total_amount' => 1999,
        'subtotal_amount' => 1999,
        'currency' => 'EUR',
    ]);

    DB::table('subscriptions')->insert([
        'number' => 'SUB-UPGRADE-1',
        'customer_email' => 'upgrade-sub@example.test',
        'product_id' => null,
        'order_id' => $order->id,
        'status' => 'active',
        'interval' => 'month',
        'interval_count' => 1,
        'price_amount' => 1999,
        'currency' => 'EUR',
        'quantity' => 1,
        'payment_gateway' => 'manual',
        'cancel_at_period_end' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::table('subscription_renewals', function ($table): void {
        $table->dropColumn([
            'charge_attempts',
            'last_charged_at',
            'next_retry_at',
            'last_error',
            'auto_charge_attempted',
            'require_manual_payment',
            'failure_notified_at',
        ]);
    });
    Schema::table('subscriptions', function ($table): void {
        $table->dropColumn('payment_gateway');
    });
    DB::table('migrations')->where('migration', '2026_08_14_100000_add_subscription_auto_charge_columns')->delete();

    expect(Schema::hasColumn('subscriptions', 'payment_gateway'))->toBeFalse();

    Artisan::call('migrate');
    app(ModuleManager::class)->enable('subscriptions');

    expect(Schema::hasColumn('subscriptions', 'payment_gateway'))->toBeTrue()
        ->and(Schema::hasColumn('subscription_renewals', 'charge_attempts'))->toBeTrue()
        ->and(DB::table('subscriptions')->where('number', 'SUB-UPGRADE-1')->value('customer_email'))
        ->toBe('upgrade-sub@example.test')
        ->and(Order::query()->whereKey($order->id)->value('total_amount'))->toBe(1999);
});
