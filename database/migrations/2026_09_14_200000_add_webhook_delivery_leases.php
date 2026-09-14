<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->string('lease_token', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->index(['status', 'lease_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['status', 'lease_expires_at']);
            $table->dropColumn(['lease_token', 'lease_expires_at']);
        });
    }
};
