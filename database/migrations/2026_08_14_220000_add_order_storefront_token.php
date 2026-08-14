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
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'storefront_token')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('storefront_token', 64)->nullable()->unique('orders_storefront_token_unique')->after('idempotency_key');
        });

        $ids = DB::table('orders')->whereNull('storefront_token')->pluck('id');
        foreach ($ids as $id) {
            DB::table('orders')->where('id', $id)->update([
                'storefront_token' => bin2hex(random_bytes(32)),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'storefront_token')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_storefront_token_unique');
            $table->dropColumn('storefront_token');
        });
    }
};
