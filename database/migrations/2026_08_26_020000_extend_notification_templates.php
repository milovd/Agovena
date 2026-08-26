<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->string('mail_format', 16)->default('plain')->after('body');
            $table->string('notification_title')->nullable()->after('mail_format');
            $table->text('notification_body')->nullable()->after('notification_title');
            $table->boolean('mail_enabled')->default(true)->after('enabled');
            $table->boolean('in_app_enabled')->default(true)->after('mail_enabled');
            $table->boolean('push_enabled')->default(true)->after('in_app_enabled');
            $table->boolean('user_choice')->default(false)->after('push_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'mail_format',
                'notification_title',
                'notification_body',
                'mail_enabled',
                'in_app_enabled',
                'push_enabled',
                'user_choice',
            ]);
        });
    }
};
