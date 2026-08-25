<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_credit_accounts')) {
            return;
        }

        Schema::table('customer_credit_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_credit_accounts', 'reserved_amount')) {
                $table->unsignedBigInteger('reserved_amount')->default(0)->after('balance_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_credit_accounts')) {
            return;
        }

        Schema::table('customer_credit_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_credit_accounts', 'reserved_amount')) {
                $table->dropColumn('reserved_amount');
            }
        });
    }
};
