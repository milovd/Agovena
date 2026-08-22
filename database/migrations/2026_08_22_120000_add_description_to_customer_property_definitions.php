<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_property_definitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_property_definitions', 'description')) {
                $table->text('description')->nullable()->after('label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_property_definitions', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_property_definitions', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
