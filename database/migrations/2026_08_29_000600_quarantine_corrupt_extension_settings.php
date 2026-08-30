<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extension_settings', function (Blueprint $table): void {
            $table->boolean('is_corrupt')->default(false)->after('is_secret');
            $table->index(['extension_id', 'is_corrupt']);
        });
    }

    public function down(): void
    {
        Schema::table('extension_settings', function (Blueprint $table): void {
            $table->dropIndex(['extension_id', 'is_corrupt']);
            $table->dropColumn('is_corrupt');
        });
    }
};
