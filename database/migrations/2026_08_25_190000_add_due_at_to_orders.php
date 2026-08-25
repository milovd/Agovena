<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'due_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->timestamp('due_at')->nullable()->after('currency');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'due_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('due_at');
            });
        }
    }
};
