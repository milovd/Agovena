<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agovena_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 32);
            $table->string('agovena_id', 64);
            $table->string('composer_name')->nullable();
            $table->string('source_type', 32);
            $table->string('source_locator')->nullable();
            $table->string('version_constraint')->default('*');
            $table->string('installed_version')->nullable();
            $table->string('available_version')->nullable();
            $table->string('install_path')->nullable();
            $table->boolean('is_bundled')->default(false);
            $table->timestamps();

            $table->unique(['kind', 'agovena_id']);
            $table->unique('composer_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agovena_packages');
    }
};
