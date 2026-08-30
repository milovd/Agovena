<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'idempotency_owner_hash')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->char('idempotency_owner_hash', 64)->nullable()->index()->after('idempotency_key');
            });
        }

        DB::table('orders')
            ->whereNotNull('idempotency_key')
            ->whereNotNull('customer_id')
            ->whereNull('idempotency_owner_hash')
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)->update([
                        'idempotency_owner_hash' => hash('sha256', 'customer|'.$order->customer_id),
                    ]);
                }
            });
    }

    public function down(): void
    {
        throw new RuntimeException('Checkout idempotency owner migration is irreversible.');
    }
};
