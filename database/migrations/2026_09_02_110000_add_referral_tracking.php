<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('window_days')->nullable()->after('expires_at');
        });

        Schema::create('referral_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->unsignedInteger('clicks_count')->default(1);
            $table->timestamp('first_clicked_at');
            $table->timestamp('last_clicked_at');
            $table->timestamp('expires_at');
            $table->foreignId('referred_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->unique(['referral_code_id', 'visitor_hash']);
            $table->index(['referral_code_id', 'last_clicked_at']);
            $table->index(['referral_code_id', 'converted_at']);
        });

        Schema::table('referral_attributions', function (Blueprint $table): void {
            $table->foreignId('referral_visit_id')->nullable()->constrained('referral_visits')->nullOnDelete();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('tracking_expires_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->unique('referral_visit_id');
            $table->index(['referral_code_id', 'purchased_at']);
        });

        DB::table('referral_attributions')
            ->where('status', 'posted')
            ->whereNull('purchased_at')
            ->update(['purchased_at' => DB::raw('credited_at')]);
    }

    public function down(): void
    {
        Schema::table('referral_attributions', function (Blueprint $table): void {
            $table->dropIndex(['referral_code_id', 'purchased_at']);
            $table->dropUnique(['referral_visit_id']);
            $table->dropConstrainedForeignId('referral_visit_id');
            $table->dropColumn(['clicked_at', 'tracking_expires_at', 'purchased_at']);
        });

        Schema::dropIfExists('referral_visits');

        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->dropColumn('window_days');
        });
    }
};
