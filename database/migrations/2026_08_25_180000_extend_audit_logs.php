<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->unique()->after('id');
            $table->string('category', 32)->nullable()->index()->after('action');
            $table->string('severity', 16)->nullable()->index()->after('category');
            $table->string('outcome', 16)->nullable()->index()->after('severity');
            $table->string('request_id', 100)->nullable()->index()->after('user_agent');
            $table->string('correlation_id', 100)->nullable()->index()->after('request_id');
            $table->string('route', 255)->nullable()->after('correlation_id');
            $table->string('method', 10)->nullable()->after('route');
            $table->unsignedSmallInteger('status_code')->nullable()->after('method');
            $table->json('before')->nullable()->after('properties');
            $table->json('after')->nullable()->after('before');
            $table->json('context')->nullable()->after('after');
            $table->char('integrity_hash', 64)->nullable()->index()->after('context');
        });

        DB::table('audit_logs')
            ->whereNull('event_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                foreach ($logs as $log) {
                    $prefix = strtolower((string) str($log->action)->before('.'));
                    $category = match ($prefix) {
                        'admin', 'appearance', 'settings', 'module', 'extension', 'role', 'user' => 'admin',
                        'auth', 'login', 'password', 'two_factor', 'api_token' => 'auth',
                        'order', 'cart', 'checkout', 'invoice', 'credit_note', 'product', 'inventory' => 'commerce',
                        'payment' => 'payment',
                        'refund' => 'refund',
                        'customer', 'privacy' => 'privacy',
                        'ticket', 'support' => 'support',
                        'notification', 'email' => 'notification',
                        'webhook' => 'webhook',
                        'security' => 'security',
                        default => 'system',
                    };

                    DB::table('audit_logs')
                        ->where('id', $log->id)
                        ->update([
                            'event_id' => (string) Str::uuid(),
                            'category' => $log->category ?? $category,
                            'severity' => $log->severity ?? 'info',
                            'outcome' => $log->outcome ?? 'success',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropUnique(['event_id']);
            $table->dropIndex(['category']);
            $table->dropIndex(['severity']);
            $table->dropIndex(['outcome']);
            $table->dropIndex(['request_id']);
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['integrity_hash']);
            $table->dropColumn([
                'event_id', 'category', 'severity', 'outcome', 'request_id',
                'correlation_id', 'route', 'method', 'status_code', 'before',
                'after', 'context', 'integrity_hash',
            ]);
        });
    }
};
