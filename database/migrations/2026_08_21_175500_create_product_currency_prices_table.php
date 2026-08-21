<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_currency_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedInteger('price_amount');
            $table->timestamps();

            $table->unique(['product_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_currency_prices');
    }
};
