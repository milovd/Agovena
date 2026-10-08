<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller VAT and company registration numbers, snapshotted at issue time like the seller name and address.
 * Issued documents keep null: they are never rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'credit_notes'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('merchant_vat_number')->nullable()->after('merchant_address');
                $table->string('merchant_company_number')->nullable()->after('merchant_vat_number');
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'credit_notes'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn(['merchant_vat_number', 'merchant_company_number']);
            });
        }
    }
};
