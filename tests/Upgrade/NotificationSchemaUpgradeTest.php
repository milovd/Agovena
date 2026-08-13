<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('notification template and email log tables can be applied onto an existing store', function () {
    Artisan::call('migrate');

    $orderId = Order::factory()->create([
        'customer_email' => 'upgrade-mail@example.test',
        'customer_name' => 'Existing Buyer',
    ])->id;

    expect(Schema::hasTable('notification_templates'))->toBeTrue()
        ->and(Schema::hasTable('email_logs'))->toBeTrue();

    DB::table('notification_templates')->insert([
        'key' => 'order_placed',
        'subject' => 'Keep me',
        'body' => 'Body',
        'enabled' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::dropIfExists('email_logs');
    Schema::dropIfExists('notification_templates');
    DB::table('migrations')->where('migration', '2026_08_13_120000_create_notification_templates_and_email_logs')->delete();

    expect(Schema::hasTable('notification_templates'))->toBeFalse()
        ->and(Order::query()->whereKey($orderId)->exists())->toBeTrue();

    Artisan::call('migrate');

    expect(Schema::hasTable('notification_templates'))->toBeTrue()
        ->and(Schema::hasTable('email_logs'))->toBeTrue()
        ->and(Order::query()->whereKey($orderId)->value('customer_email'))->toBe('upgrade-mail@example.test')
        ->and(DB::table('notification_templates')->count())->toBe(0);
});
