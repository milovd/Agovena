<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agovena_extensions', function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id')->unique();
            $table->string('version');
            $table->boolean('enabled')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('extension_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['extension_id', 'key']);
            $table->index('extension_id');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway_id', 64);
            $table->string('status', 32);
            $table->string('external_id')->nullable()->index();
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('redirect_url')->nullable();
            $table->json('request_meta')->nullable();
            $table->json('response_meta')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['gateway_id', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_id', 64);
            $table->string('external_event_id')->nullable();
            $table->string('external_payment_id')->nullable()->index();
            $table->string('status', 32);
            $table->string('processing_status', 32)->default('received');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway_id', 'external_event_id']);
            $table->index(['gateway_id', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('extension_settings');
        Schema::dropIfExists('agovena_extensions');
    }
};
