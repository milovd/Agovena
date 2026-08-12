<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Schema as it existed immediately before unified User identity.
 */
final class LegacyPreUnifySchema
{
    /**
     * @return array{
     *     owner_email: string,
     *     customer_email: string,
     *     merge_email: string,
     *     owner_id: int,
     *     customer_id: int,
     *     merge_customer_id: int,
     *     order_id: int,
     *     ticket_id: int,
     *     credit_id: int
     * }
     */
    public static function installAndSeed(): array
    {
        self::wipe();
        self::migrate();

        return self::seed();
    }

    public static function wipe(): void
    {
        DB::unprepared('PRAGMA foreign_keys = OFF');

        $tables = [];
        foreach (Schema::getTableListing() as $table) {
            $name = str_replace(['"', "'", '`'], '', (string) $table);
            if (str_contains($name, '.')) {
                $name = (string) substr($name, (int) strrpos($name, '.') + 1);
            }
            $tables[] = $name;
        }

        foreach (array_unique($tables) as $table) {
            if (in_array($table, ['sqlite_sequence', 'sqlite_stat1'], true)) {
                continue;
            }
            DB::unprepared('DROP TABLE IF EXISTS "'.$table.'"');
        }

        foreach ([
            'customer_credit_entries', 'tickets', 'orders', 'model_has_roles',
            'model_has_permissions', 'role_has_permissions', 'roles', 'permissions',
            'sessions', 'customer_password_reset_tokens', 'password_reset_tokens',
            'customers', 'staff_users', 'users',
        ] as $table) {
            DB::unprepared('DROP TABLE IF EXISTS "'.$table.'"');
        }

        DB::unprepared('PRAGMA foreign_keys = ON');
    }

    public static function migrate(): void
    {
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

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('customer_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 32);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedInteger('subtotal_amount');
            $table->unsignedInteger('total_amount');
            $table->char('currency', 3);
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->string('subject');
            $table->string('status', 20)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->string('department')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->nullableMorphs('related');
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 10);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->string('reason');
            $table->nullableMorphs('reference');
            $table->foreignId('staff_user_id')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * @return array{
     *     owner_email: string,
     *     customer_email: string,
     *     merge_email: string,
     *     owner_id: int,
     *     customer_id: int,
     *     merge_customer_id: int,
     *     order_id: int,
     *     ticket_id: int,
     *     credit_id: int
     * }
     */
    public static function seed(): array
    {
        $now = now();
        $password = Hash::make('password');

        $ownerId = DB::table('staff_users')->insertGetId([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'email_verified_at' => $now,
            'password' => $password,
            'remember_token' => Str::random(10),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $mergeStaffId = DB::table('staff_users')->insertGetId([
            'name' => 'Editor Staff',
            'email' => 'editor@example.com',
            'email_verified_at' => $now,
            'password' => Hash::make('staff-password'),
            'remember_token' => Str::random(10),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Ada Customer',
            'email' => 'ada@example.com',
            'email_verified_at' => $now,
            'password' => $password,
            'remember_token' => Str::random(10),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $mergeCustomerId = DB::table('customers')->insertGetId([
            'name' => 'Editor Customer',
            'email' => 'editor@example.com',
            'email_verified_at' => $now,
            'password' => Hash::make('customer-password'),
            'remember_token' => Str::random(10),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'dashboard.view',
            'guard_name' => 'staff',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'owner',
            'guard_name' => 'staff',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $editorRoleId = DB::table('roles')->insertGetId([
            'name' => 'editor',
            'guard_name' => 'staff',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('role_has_permissions')->insert([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);
        DB::table('role_has_permissions')->insert([
            'permission_id' => $permissionId,
            'role_id' => $editorRoleId,
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => 'App\\Models\\StaffUser',
            'model_id' => $ownerId,
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $editorRoleId,
            'model_type' => 'App\\Models\\StaffUser',
            'model_id' => $mergeStaffId,
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'number' => 'ORD-1001',
            'status' => 'pending',
            'customer_name' => 'Ada Customer',
            'customer_email' => 'ada@example.com',
            'customer_id' => $customerId,
            'subtotal_amount' => 1000,
            'total_amount' => 1000,
            'currency' => 'EUR',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ticketId = DB::table('tickets')->insertGetId([
            'number' => 'TCK-1001',
            'customer_id' => $customerId,
            'staff_user_id' => $ownerId,
            'subject' => 'Need help',
            'status' => 'open',
            'priority' => 'normal',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $creditId = DB::table('customer_credit_entries')->insertGetId([
            'customer_id' => $customerId,
            'entry_type' => 'credit',
            'amount' => 500,
            'balance_after' => 500,
            'reason' => 'service_recovery',
            'staff_user_id' => $ownerId,
            'created_at' => $now,
        ]);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $ownerId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('legacy-staff-session'),
            'last_activity' => time(),
        ]);

        DB::table('customer_password_reset_tokens')->insert([
            'email' => 'ada@example.com',
            'token' => Hash::make('reset-token'),
            'created_at' => $now,
        ]);

        return [
            'owner_email' => 'owner@example.com',
            'customer_email' => 'ada@example.com',
            'merge_email' => 'editor@example.com',
            'owner_id' => $ownerId,
            'customer_id' => $customerId,
            'merge_customer_id' => $mergeCustomerId,
            'order_id' => $orderId,
            'ticket_id' => $ticketId,
            'credit_id' => $creditId,
        ];
    }
}
