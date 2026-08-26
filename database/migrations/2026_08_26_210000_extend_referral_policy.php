<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->unsignedInteger('max_uses')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('reward_amount')->default(0);
            $table->char('reward_currency', 3)->nullable();
            $table->boolean('fraud_review_required')->default(false);
            $table->index(['is_active', 'expires_at']);
        });

        Schema::table('referral_attributions', function (Blueprint $table): void {
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('reward_amount')->default(0);
            $table->char('reward_currency', 3)->nullable();
            $table->boolean('fraud_review_required')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('referral_attributions', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn([
                'status', 'reward_amount', 'reward_currency', 'fraud_review_required',
                'reviewed_at', 'credited_at',
            ]);
        });

        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'expires_at']);
            $table->dropColumn([
                'max_uses', 'expires_at', 'reward_amount', 'reward_currency', 'fraud_review_required',
            ]);
        });
    }
};
