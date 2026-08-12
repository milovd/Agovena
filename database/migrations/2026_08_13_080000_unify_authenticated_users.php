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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $this->copyStaffUsers();
        $this->copyCustomers();
        $this->relinkSpatieRoles();

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        $this->linkCustomerProfiles();

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique('user_id');
        });

        Schema::disableForeignKeyConstraints();

        if (Schema::hasColumn('tickets', 'staff_user_id')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropForeign(['staff_user_id']);
            });
            Schema::table('tickets', function (Blueprint $table): void {
                $table->foreign('staff_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('customer_credit_entries', 'staff_user_id')) {
            Schema::table('customer_credit_entries', function (Blueprint $table): void {
                $table->dropForeign(['staff_user_id']);
            });
            Schema::table('customer_credit_entries', function (Blueprint $table): void {
                $table->foreign('staff_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        $dropCustomerAuthColumns = array_values(array_filter([
            Schema::hasColumn('customers', 'password') ? 'password' : null,
            Schema::hasColumn('customers', 'remember_token') ? 'remember_token' : null,
            Schema::hasColumn('customers', 'email_verified_at') ? 'email_verified_at' : null,
            Schema::hasColumn('customers', 'anonymized_at') ? 'anonymized_at' : null,
            Schema::hasColumn('customers', 'deletion_requested_at') ? 'deletion_requested_at' : null,
        ]));

        if ($dropCustomerAuthColumns !== []) {
            Schema::table('customers', function (Blueprint $table) use ($dropCustomerAuthColumns): void {
                $table->dropColumn($dropCustomerAuthColumns);
            });
        }

        Schema::dropIfExists('staff_users');
        Schema::dropIfExists('customer_password_reset_tokens');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
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

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
        });

        Schema::dropIfExists('users');
    }

    private function copyStaffUsers(): void
    {
        if (! Schema::hasTable('staff_users')) {
            return;
        }

        $rows = DB::table('staff_users')->orderBy('id')->get();
        foreach ($rows as $row) {
            DB::table('users')->insert([
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'email_verified_at' => $row->email_verified_at,
                'password' => $row->password,
                'two_factor_secret' => $row->two_factor_secret,
                'two_factor_recovery_codes' => $row->two_factor_recovery_codes,
                'two_factor_confirmed_at' => $row->two_factor_confirmed_at,
                'remember_token' => $row->remember_token,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        $this->resetSequence('users');
    }

    private function copyCustomers(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $rows = DB::table('customers')->orderBy('id')->get();
        foreach ($rows as $row) {
            $existing = DB::table('users')->where('email', $row->email)->first();
            if ($existing !== null) {
                continue;
            }

            $password = $row->password ?? '';
            if ($password === '') {
                $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            }

            DB::table('users')->insert([
                'name' => $row->name,
                'email' => $row->email,
                'email_verified_at' => $row->email_verified_at ?? null,
                'password' => $password,
                'anonymized_at' => $row->anonymized_at ?? null,
                'deletion_requested_at' => $row->deletion_requested_at ?? null,
                'remember_token' => $row->remember_token ?? null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        $this->resetSequence('users');
    }

    private function linkCustomerProfiles(): void
    {
        $customers = DB::table('customers')->orderBy('id')->get();
        foreach ($customers as $row) {
            $user = DB::table('users')->where('email', $row->email)->first();
            if ($user === null) {
                continue;
            }
            DB::table('customers')->where('id', $row->id)->update(['user_id' => $user->id]);
        }

        $linked = DB::table('customers')->whereNotNull('user_id')->pluck('user_id')->all();
        $users = DB::table('users')->orderBy('id')->get();
        foreach ($users as $user) {
            if (in_array($user->id, $linked, true)) {
                continue;
            }
            DB::table('customers')->insert([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        }
    }

    private function relinkSpatieRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        DB::table('roles')->where('guard_name', 'staff')->update(['guard_name' => 'web']);
        DB::table('permissions')->where('guard_name', 'staff')->update(['guard_name' => 'web']);

        if (! Schema::hasTable('model_has_roles')) {
            return;
        }

        $staffClass = 'App\\Models\\StaffUser';
        $userClass = 'App\\Models\\User';
        $rows = DB::table('model_has_roles')->where('model_type', $staffClass)->get();
        foreach ($rows as $row) {
            $staff = Schema::hasTable('staff_users')
                ? DB::table('staff_users')->where('id', $row->model_id)->first()
                : null;
            if ($staff === null) {
                continue;
            }
            $user = DB::table('users')->where('email', $staff->email)->first();
            if ($user === null) {
                continue;
            }
            DB::table('model_has_roles')->where('role_id', $row->role_id)
                ->where('model_type', $staffClass)
                ->where('model_id', $row->model_id)
                ->update([
                    'model_type' => $userClass,
                    'model_id' => $user->id,
                ]);
        }

        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->where('model_type', $staffClass)
                ->update(['model_type' => $userClass]);
        }
    }

    private function resetSequence(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();
        $max = (int) (DB::table($table)->max('id') ?? 0);
        if ($driver === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), GREATEST({$max}, 1))");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE '.$table.' AUTO_INCREMENT = '.($max + 1));
        }
    }
};
