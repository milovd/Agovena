<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32);
            $table->string('entity', 32);
            $table->string('mode', 16);
            $table->string('status', 16);
            $table->unsignedInteger('read')->default(0);
            $table->unsignedInteger('valid')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'entity', 'status']);
        });

        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->string('entity', 32);
            $table->string('external_id')->nullable();
            $table->string('status', 16);
            $table->json('payload')->nullable();
            $table->string('imported_model_type')->nullable();
            $table->unsignedBigInteger('imported_model_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['import_run_id', 'line']);
            $table->index(['external_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_runs');
    }
};
