<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_items')
            || Schema::hasColumn('order_items', 'provisioning_provider_settings_snapshot')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $column = $table->text('provisioning_provider_settings_snapshot')->nullable();
            if (Schema::hasColumn('order_items', 'provisioning_server_settings_snapshot')) {
                $column->after('provisioning_server_settings_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_items')
            || ! Schema::hasColumn('order_items', 'provisioning_provider_settings_snapshot')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('provisioning_provider_settings_snapshot');
        });
    }
};
