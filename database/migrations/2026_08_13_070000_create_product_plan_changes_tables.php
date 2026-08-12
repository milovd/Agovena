<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_plan_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('from_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('to_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('change_type', 16);
            $table->boolean('is_active')->default(true);
            $table->string('timing', 16)->default('immediate');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['from_product_id', 'to_product_id']);
        });

        Schema::create('product_plan_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_plan_change_id')->constrained('product_plan_changes')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->foreignId('from_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('to_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('timing', 16);
            $table->string('status', 16)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_plan_change_requests');
        Schema::dropIfExists('product_plan_changes');
    }
};
