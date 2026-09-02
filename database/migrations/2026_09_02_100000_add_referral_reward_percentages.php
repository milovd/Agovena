<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->unsignedTinyInteger('reward_percentage')->nullable();
        });

        Schema::table('referral_attributions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('reward_percentage')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('referral_attributions', function (Blueprint $table): void {
            $table->dropColumn('reward_percentage');
        });

        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->dropColumn('reward_percentage');
        });
    }
};
