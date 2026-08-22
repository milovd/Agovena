<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait SimulatesPendingSchema
{
    protected function dropCustomerPropertySchema(): void
    {
        Schema::dropIfExists('customer_property_values');
        Schema::dropIfExists('customer_property_definitions');

        if (Schema::hasColumn('orders', 'custom_properties_snapshot')) {
            Schema::table('orders', function ($table): void {
                $table->dropColumn('custom_properties_snapshot');
            });
        }

        if (Schema::hasColumn('invoices', 'custom_properties_snapshot')) {
            Schema::table('invoices', function ($table): void {
                $table->dropColumn('custom_properties_snapshot');
            });
        }

        DB::table('migrations')->whereIn('migration', [
            '2026_08_13_090000_create_customer_custom_properties_tables',
            '2026_08_22_120000_add_description_to_customer_property_definitions',
        ])->delete();
    }
}
