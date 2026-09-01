<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('queue')->index();
            $table->text('payload_encrypted');
            $table->string('source_connection')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->string('claim_token')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['completed_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_outboxes');
    }
};
