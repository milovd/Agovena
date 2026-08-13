<?php

declare(strict_types=1);

use App\Agovena\Modules\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('generic carrier shipment columns can be applied onto existing shipment tables', function () {
    Artisan::call('migrate');
    app(ModuleManager::class)->enable('shipping');

    expect(Schema::hasColumn('shipments', 'carrier_id'))->toBeTrue()
        ->and(Schema::hasColumn('shipments', 'external_ref'))->toBeTrue()
        ->and(Schema::hasColumn('shipments', 'label_path'))->toBeTrue();

    Schema::table('shipments', function ($table): void {
        $table->dropColumn(['carrier_id', 'external_ref', 'label_path']);
    });
    DB::table('migrations')->where('migration', '2026_08_14_120000_add_generic_carrier_shipment_columns')->delete();

    expect(Schema::hasColumn('shipments', 'carrier_id'))->toBeFalse();

    Artisan::call('migrate');
    app(ModuleManager::class)->enable('shipping');

    expect(Schema::hasColumn('shipments', 'carrier_id'))->toBeTrue()
        ->and(Schema::hasColumn('shipments', 'external_ref'))->toBeTrue()
        ->and(Schema::hasColumn('shipments', 'label_path'))->toBeTrue();
});
