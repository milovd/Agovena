<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'reconciliation_status')) {
                $table->string('reconciliation_status', 32)
                    ->nullable()
                    ->index('payments_reconciliation_status_index');
            }

            if (! Schema::hasColumn('payments', 'reconciliation_meta')) {
                $table->json('reconciliation_meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (Schema::hasColumn('payments', 'reconciliation_meta')) {
                $table->dropColumn('reconciliation_meta');
            }

            if (Schema::hasColumn('payments', 'reconciliation_status')) {
                $table->dropIndex('payments_reconciliation_status_index');
                $table->dropColumn('reconciliation_status');
            }
        });
    }
};
