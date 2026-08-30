<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        throw new RuntimeException('Intentional partial package migration failure.');
    }

    public function down(): void
    {
        Schema::dropIfExists('partial_migrate_records');
    }
};
