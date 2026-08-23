<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('event tables can be applied onto existing orders', function () {
    Artisan::call('migrate');

    $order = Order::factory()->create([
        'customer_email' => 'upgrade-events@example.test',
        'customer_name' => 'Existing Buyer',
        'status' => 'paid',
        'total_amount' => 4200,
        'subtotal_amount' => 4200,
        'currency' => 'EUR',
    ]);

    installAndEnableModule('events');

    expect(Schema::hasTable('events'))->toBeTrue()
        ->and(Schema::hasTable('event_tickets'))->toBeTrue();

    Schema::dropIfExists('event_tickets');
    Schema::dropIfExists('event_ticket_types');
    Schema::dropIfExists('event_performances');
    Schema::dropIfExists('events');
    DB::table('migrations')->where('migration', '2026_08_13_150000_create_event_tables')->delete();

    expect(Schema::hasTable('events'))->toBeFalse()
        ->and(Order::query()->whereKey($order->id)->value('customer_email'))->toBe('upgrade-events@example.test');

    Artisan::call('migrate');
    installAndEnableModule('events');

    expect(Schema::hasTable('events'))->toBeTrue()
        ->and(Schema::hasTable('event_tickets'))->toBeTrue()
        ->and(Order::query()->whereKey($order->id)->value('total_amount'))->toBe(4200);
});
