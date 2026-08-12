<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('rate_bps');
            $table->char('country', 2)->nullable();
            $table->string('region')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('applies_to_shipping')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'country']);
        });

        Schema::create('discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('type', 16);
            $table->unsignedInteger('value');
            $table->char('currency', 3)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('min_subtotal_amount')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('discount_amount')->default(0)->after('shipping_method_label');
            $table->unsignedInteger('tax_amount')->default(0)->after('discount_amount');
            $table->string('discount_code')->nullable()->after('tax_amount');
            $table->string('tax_rate_name')->nullable()->after('discount_code');
            $table->unsignedInteger('tax_rate_bps')->nullable()->after('tax_rate_name');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedInteger('discount_amount')->default(0)->after('subtotal_amount');
        });

        Schema::create('discount_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discount_code_id')->constrained('discount_codes')->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('code');
            $table->unsignedInteger('amount');
            $table->timestamps();

            $table->index(['discount_code_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_redemptions');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('discount_amount');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'discount_amount',
                'tax_amount',
                'discount_code',
                'tax_rate_name',
                'tax_rate_bps',
            ]);
        });

        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('tax_rates');
    }
};
