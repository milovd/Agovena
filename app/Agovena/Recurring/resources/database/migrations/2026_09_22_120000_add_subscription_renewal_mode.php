<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'renewal_mode')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->string('renewal_mode', 16)->default('automatic')->after('payment_gateway');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'renewal_mode')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->dropColumn('renewal_mode');
            });
        }
    }
};
