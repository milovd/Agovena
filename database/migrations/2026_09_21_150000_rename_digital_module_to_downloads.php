<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('agovena_modules')->where('module_id', 'digital')->first();
        $current = DB::table('agovena_modules')->where('module_id', 'downloads')->first();

        if ($legacy === null) {
            return;
        }

        if ($current === null) {
            DB::table('agovena_modules')
                ->where('module_id', 'digital')
                ->update(['module_id' => 'downloads']);

            return;
        }

        DB::table('agovena_modules')->where('module_id', 'digital')->delete();
    }

    public function down(): void
    {
        if (DB::table('agovena_modules')->where('module_id', 'digital')->exists()) {
            return;
        }

        DB::table('agovena_modules')
            ->where('module_id', 'downloads')
            ->update(['module_id' => 'digital']);
    }
};
