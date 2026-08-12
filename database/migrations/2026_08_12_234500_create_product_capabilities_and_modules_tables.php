<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('capability', 64);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'capability']);
            $table->index('capability');
        });

        Schema::create('agovena_modules', function (Blueprint $table): void {
            $table->id();
            $table->string('module_id', 64)->unique();
            $table->string('version', 32);
            $table->boolean('enabled')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agovena_modules');
        Schema::dropIfExists('product_capabilities');
    }
};
