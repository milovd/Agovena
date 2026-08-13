<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'credit_amount')) {
                $table->unsignedBigInteger('credit_amount')->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('invoices', 'tax_rate_name')) {
                $table->string('tax_rate_name')->nullable()->after('tax_amount');
            }
            if (! Schema::hasColumn('invoices', 'tax_rate_bps')) {
                $table->unsignedInteger('tax_rate_bps')->nullable()->after('tax_rate_name');
            }
            if (! Schema::hasColumn('invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('issued_at');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_items', 'kind')) {
                $table->string('kind', 32)->default('product')->after('invoice_id');
            }
            if (! Schema::hasColumn('invoice_items', 'options_snapshot')) {
                $table->json('options_snapshot')->nullable()->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            foreach (['credit_amount', 'tax_rate_name', 'tax_rate_bps', 'paid_at'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            foreach (['kind', 'options_snapshot'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
