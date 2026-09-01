<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('queue_outboxes') || Schema::hasColumn('queue_outboxes', 'claim_token')) {
            return;
        }

        Schema::table('queue_outboxes', function (Blueprint $table): void {
            $table->string('claim_token')->nullable()->after('claimed_at')->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('queue_outboxes') && Schema::hasColumn('queue_outboxes', 'claim_token')) {
            Schema::table('queue_outboxes', function (Blueprint $table): void {
                $table->dropColumn('claim_token');
            });
        }
    }
};
