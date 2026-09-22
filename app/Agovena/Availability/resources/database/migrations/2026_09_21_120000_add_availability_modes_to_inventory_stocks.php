<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_stocks', 'availability_mode')) {
                $table->string('availability_mode', 32)->default('finite')->after('product_id');
            }
            if (! Schema::hasColumn('inventory_stocks', 'provider_key')) {
                $table->string('provider_key', 128)->nullable()->after('availability_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            foreach (['provider_key', 'availability_mode'] as $column) {
                if (Schema::hasColumn('inventory_stocks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
