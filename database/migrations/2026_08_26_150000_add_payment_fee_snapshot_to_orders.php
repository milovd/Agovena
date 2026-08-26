<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('payment_fee_amount')->default(0)->after('tax_amount');
            $table->json('payment_fee_snapshot')->nullable()->after('payment_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_fee_amount', 'payment_fee_snapshot']);
        });
    }
};
