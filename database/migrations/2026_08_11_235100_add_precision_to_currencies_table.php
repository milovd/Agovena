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
        if (! Schema::hasColumn('currencies', 'precision')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->unsignedTinyInteger('precision')->default(2)->after('suffix');
            });
        }

        DB::table('currencies')->whereNull('precision')->update(['precision' => 2]);

        $now = now();
        if (! DB::table('currencies')->where('code', 'JPY')->exists()) {
            DB::table('currencies')->insert([
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'prefix' => '¥',
                'suffix' => '',
                'precision' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('currencies')->where('code', 'JPY')->update(['precision' => 0]);
        }
    }

    public function down(): void
    {
        DB::table('currencies')->where('code', 'JPY')->delete();

        if (Schema::hasColumn('currencies', 'precision')) {
            Schema::table('currencies', function (Blueprint $table): void {
                $table->dropColumn('precision');
            });
        }
    }
};
