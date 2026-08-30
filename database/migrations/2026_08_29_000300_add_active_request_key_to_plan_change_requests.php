<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_plan_change_requests', 'active_request_key')) {
            Schema::table('product_plan_change_requests', function (Blueprint $table): void {
                $table->string('active_request_key', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Active plan change request key migration is irreversible.');
    }
};
