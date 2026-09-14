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
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->boolean('retention_exempt')->default(false)->after('processing_status');
            $table->index(['gateway_id', 'retention_exempt']);
        });

        DB::table('payment_webhook_events')
            ->where('gateway_id', 'tebex')
            ->update(['retention_exempt' => true]);
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->dropIndex(['gateway_id', 'retention_exempt']);
            $table->dropColumn('retention_exempt');
        });
    }
};
