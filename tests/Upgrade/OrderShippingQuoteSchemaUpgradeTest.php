<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('shipping carrier snapshot columns and order indexes can be applied onto existing orders', function () {
    Artisan::call('migrate');

    expect(Schema::hasColumn('orders', 'shipping_carrier_id'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'shipping_service_code'))->toBeTrue();

    $mysql = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    if ($mysql) {
        Schema::table('orders', function ($table): void {
            $table->dropForeign(['customer_id']);
        });
    }

    Schema::table('orders', function ($table): void {
        if (Schema::hasIndex('orders', 'orders_customer_status_created_index')) {
            $table->dropIndex('orders_customer_status_created_index');
        }
        if (Schema::hasIndex('orders', 'orders_status_created_index')) {
            $table->dropIndex('orders_status_created_index');
        }
        $table->dropColumn(['shipping_carrier_id', 'shipping_service_code']);
    });

    if ($mysql) {
        Schema::table('orders', function ($table): void {
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    DB::table('migrations')->where('migration', '2026_08_14_200000_add_shipping_quote_snapshot_and_order_indexes')->delete();

    expect(Schema::hasColumn('orders', 'shipping_carrier_id'))->toBeFalse()
        ->and(Schema::hasIndex('orders', 'orders_status_created_index'))->toBeFalse();

    Artisan::call('migrate');

    expect(Schema::hasColumn('orders', 'shipping_carrier_id'))->toBeTrue()
        ->and(Schema::hasColumn('orders', 'shipping_service_code'))->toBeTrue()
        ->and(Schema::hasIndex('orders', 'orders_status_created_index'))->toBeTrue()
        ->and(Order::query()->count())->toBeGreaterThanOrEqual(0);
});
