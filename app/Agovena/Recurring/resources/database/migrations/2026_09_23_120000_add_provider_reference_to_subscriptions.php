<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions') || Schema::hasColumn('subscriptions', 'provider_reference')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('provider_reference')->nullable()->after('renewal_mode');
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasColumn('subscriptions', 'provider_reference')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['provider_reference']);
            $table->dropColumn('provider_reference');
        });
    }
};
