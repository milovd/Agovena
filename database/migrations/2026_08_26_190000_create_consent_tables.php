<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('consent_version', 32);
            $table->string('choice', 32);
            $table->string('source', 32);
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('consent_event_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('consent_event_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->boolean('decision');
            $table->timestamps();

            $table->unique(['consent_event_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_event_categories');
        Schema::dropIfExists('consent_events');
    }
};
