<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('personal access tokens table can be applied onto existing users and orders', function () {
    Artisan::call('migrate');

    $order = Order::factory()->create([
        'customer_email' => 'upgrade-api@example.test',
        'customer_name' => 'Existing Buyer',
        'status' => 'paid',
        'total_amount' => 1800,
        'subtotal_amount' => 1800,
        'currency' => 'EUR',
    ]);

    expect(Schema::hasTable('personal_access_tokens'))->toBeTrue();

    Schema::dropIfExists('personal_access_tokens');
    DB::table('migrations')->where('migration', '2026_08_13_140000_create_personal_access_tokens_table')->delete();

    expect(Schema::hasTable('personal_access_tokens'))->toBeFalse()
        ->and(Order::query()->whereKey($order->id)->value('customer_email'))->toBe('upgrade-api@example.test');

    Artisan::call('migrate');

    expect(Schema::hasTable('personal_access_tokens'))->toBeTrue()
        ->and(Order::query()->whereKey($order->id)->value('total_amount'))->toBe(1800);
});
