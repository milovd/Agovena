<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['customer_id', 'is_active']);
        });

        Schema::create('referral_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('referral_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('referrer_customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('referred_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('code_snapshot', 64);
            $table->timestamps();
            $table->index(['referrer_customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_attributions');
        Schema::dropIfExists('referral_codes');
    }
};
