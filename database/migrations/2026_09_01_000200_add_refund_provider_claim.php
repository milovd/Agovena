<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('refunds', 'provider_claimed_at')) {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->timestamp('provider_claimed_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('refunds', 'provider_claimed_at')) {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->dropColumn('provider_claimed_at');
            });
        }
    }
};
