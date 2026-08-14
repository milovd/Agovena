<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        throw new RuntimeException('Intentional broken package migration.');
    }

    public function down(): void
    {
        //
    }
};
