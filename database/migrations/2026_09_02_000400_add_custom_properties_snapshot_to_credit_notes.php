<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->json('custom_properties_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('credit_notes', function (Blueprint $table): void {
            $table->dropColumn('custom_properties_snapshot');
        });
    }
};
