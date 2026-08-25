<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->decimal('exchange_rate', 18, 8)->default(1)->after('precision');
        });

        // Default to 1.00000000 (parity with base). Sync mid-market rates from Admin → Currencies.
        // Do not seed baked-in FX tables here.
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->dropColumn('exchange_rate');
        });
    }
};
