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
            $table->string('failure_code', 64)->nullable()->after('last_error');
            $table->timestamp('failed_at')->nullable()->after('next_attempt_at');
            $table->timestamp('dead_lettered_at')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropColumn(['failure_code', 'failed_at', 'dead_lettered_at']);
        });
    }
};
