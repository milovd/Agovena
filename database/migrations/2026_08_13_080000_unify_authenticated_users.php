<?php

declare(strict_types=1);

use App\Agovena\Auth\UnifyAuthenticatedUsers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (new UnifyAuthenticatedUsers)();
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_users')) {
            Schema::create('staff_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'user_id')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        Schema::dropIfExists('users');
    }
};
