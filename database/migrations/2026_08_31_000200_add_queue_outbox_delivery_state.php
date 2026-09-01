<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('queue_outboxes') || Schema::hasColumn('queue_outboxes', 'delivery_state')) {
            return;
        }

        Schema::table('queue_outboxes', function (Blueprint $table): void {
            $table->string('delivery_state')->default('pending')->after('claimed_at');
            $table->index(['delivery_state', 'available_at']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('queue_outboxes') || ! Schema::hasColumn('queue_outboxes', 'delivery_state')) {
            return;
        }

        Schema::table('queue_outboxes', function (Blueprint $table): void {
            $table->dropIndex(['delivery_state', 'available_at']);
            $table->dropColumn('delivery_state');
        });
    }
};
