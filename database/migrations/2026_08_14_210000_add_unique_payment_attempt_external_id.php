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
        if (! Schema::hasTable('payment_attempts')) {
            return;
        }

        $duplicates = DB::table('payment_attempts as later')
            ->join('payment_attempts as earlier', function ($join): void {
                $join->on('later.gateway_id', '=', 'earlier.gateway_id')
                    ->on('later.external_id', '=', 'earlier.external_id')
                    ->whereNotNull('later.external_id')
                    ->whereColumn('later.id', '>', 'earlier.id');
            })
            ->pluck('later.id');

        if ($duplicates->isNotEmpty()) {
            DB::table('payment_attempts')->whereIn('id', $duplicates)->delete();
        }

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->unique(['gateway_id', 'external_id'], 'payment_attempts_gateway_external_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_attempts')) {
            return;
        }

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropUnique('payment_attempts_gateway_external_unique');
        });
    }
};
