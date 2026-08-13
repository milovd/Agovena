<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label');
            $table->string('type', 32);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('price_adjustment_amount')->default(0);
            $table->json('constraints')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });

        Schema::create('product_option_choices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_option_id')->constrained('product_options')->cascadeOnDelete();
            $table->string('value', 64);
            $table->string('label');
            $table->unsignedInteger('price_adjustment_amount')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_option_id', 'value']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('options_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('options_snapshot');
        });
        Schema::dropIfExists('product_option_choices');
        Schema::dropIfExists('product_options');
    }
};
