<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->decimal('exchange_rate', 18, 8)->default(1)->after('precision');
        });

        // Units of this currency per 1 unit of base (EUR by default). Editable in Admin.
        $defaults = [
            'EUR' => '1.00000000',
            'USD' => '1.08000000',
            'GBP' => '0.86000000',
            'JPY' => '160.00000000',
        ];

        foreach ($defaults as $code => $rate) {
            DB::table('currencies')->where('code', $code)->update(['exchange_rate' => $rate]);
        }
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table): void {
            $table->dropColumn('exchange_rate');
        });
    }
};
