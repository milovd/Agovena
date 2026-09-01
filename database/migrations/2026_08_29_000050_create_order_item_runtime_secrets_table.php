<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_runtime_secrets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->text('value_encrypted');
            $table->timestamps();
            $table->unique(['order_item_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_runtime_secrets');
    }
};
