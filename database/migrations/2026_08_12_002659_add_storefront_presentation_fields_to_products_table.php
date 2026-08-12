<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->after('name');
            $table->boolean('show_details')->default(true)->after('specifications');
            $table->boolean('show_specifications')->default(true)->after('show_details');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['subtitle', 'show_details', 'show_specifications']);
        });
    }
};
