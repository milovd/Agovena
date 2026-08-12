<?php

declare(strict_types=1);

namespace App\Agovena\Auth;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Upgrade StaffUser + Customer identities onto a single users table.
 *
 * Safe to run on:
 * - existing installs that still have staff_users
 * - fresh installs that already created users in 0001 (no-op)
 * - a retry after a partial MySQL run (users exists, staff_users still present)
 */
final class UnifyAuthenticatedUsers
{
    private const STAFF_MODEL = 'App\\Models\\StaffUser';

    private const USER_MODEL = 'App\\Models\\User';

    public function __invoke(): void
    {
        if ($this->alreadyUnified()) {
            return;
        }

        $this->ensureUsersTable();

        $this->withDataTransaction(function (): void {
            $this->copyStaffUsers();
            $this->copyCustomers();
            $this->relinkSpatieRoles();
        });

        $this->ensureCustomerUserIdColumn();

        $this->withDataTransaction(function (): void {
            $this->linkCustomerProfiles();
            $this->remapStaffUserIdColumns();
        });

        $this->ensureCustomerUserIdUnique();
        $this->retargetStaffUserForeignKeys();
        $this->dropCustomerAuthColumns();
        $this->resetSequence('users');

        $this->assertDestinationReady();
        $this->invalidateLegacyCredentials();
        $this->dropSourceIdentityTables();
        $this->forgetPermissionCache();
    }

    private function alreadyUnified(): bool
    {
        return Schema::hasTable('users')
            && ! Schema::hasTable('staff_users')
            && Schema::hasColumn('customers', 'user_id')
            && ! Schema::hasColumn('customers', 'password');
    }

    private function ensureUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

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
    }

    private function copyStaffUsers(): void
    {
        if (! Schema::hasTable('staff_users')) {
            return;
        }

        $rows = DB::table('staff_users')->orderBy('id')->get();
        foreach ($rows as $row) {
            if (DB::table('users')->where('email', $row->email)->exists()) {
                continue;
            }

            $payload = [
                'name' => $row->name,
                'email' => $row->email,
                'email_verified_at' => $row->email_verified_at,
                'password' => $row->password,
                'two_factor_secret' => $row->two_factor_secret ?? null,
                'two_factor_recovery_codes' => $row->two_factor_recovery_codes ?? null,
                'two_factor_confirmed_at' => $row->two_factor_confirmed_at ?? null,
                'anonymized_at' => $row->anonymized_at ?? null,
                'deletion_requested_at' => $row->deletion_requested_at ?? null,
                'remember_token' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];

            if (! DB::table('users')->where('id', $row->id)->exists()) {
                $payload['id'] = $row->id;
            }

            DB::table('users')->insert($payload);
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
            if (DB::table('users')->where('email', $row->email)->exists()) {
                continue;
            }

            $password = (string) ($row->password ?? '');
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
                'remember_token' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        $this->resetSequence('users');
    }

    private function ensureCustomerUserIdColumn(): void
    {
        if (! Schema::hasTable('customers') || Schema::hasColumn('customers', 'user_id')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable();
        });
    }

    private function linkCustomerProfiles(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $customers = DB::table('customers')->orderBy('id')->get();
        foreach ($customers as $row) {
            $user = DB::table('users')->where('email', $row->email)->first();
            if ($user === null) {
                continue;
            }
            DB::table('customers')->where('id', $row->id)->update(['user_id' => $user->id]);
        }

        $linked = array_map(
            static fn (mixed $id): int => (int) $id,
            DB::table('customers')->whereNotNull('user_id')->pluck('user_id')->all(),
        );
        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            if (in_array((int) $user->id, $linked, true)) {
                continue;
            }

            $insert = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            if (Schema::hasColumn('customers', 'password')) {
                $insert['password'] = $user->password;
            }

            DB::table('customers')->insert($insert);
        }
    }

    /**
     * @return array<int, int>
     */
    private function staffIdToUserId(): array
    {
        if (! Schema::hasTable('staff_users')) {
            return [];
        }

        $map = [];
        foreach (DB::table('staff_users')->orderBy('id')->get() as $staff) {
            $user = DB::table('users')->where('email', $staff->email)->first();
            if ($user === null) {
                continue;
            }
            $map[(int) $staff->id] = (int) $user->id;
        }

        return $map;
    }

    private function remapStaffUserIdColumns(): void
    {
        $map = $this->staffIdToUserId();
        if ($map === []) {
            return;
        }

        foreach (['tickets', 'customer_credit_entries'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'staff_user_id')) {
                continue;
            }

            $rows = DB::table($table)->whereNotNull('staff_user_id')->get(['id', 'staff_user_id']);
            foreach ($rows as $row) {
                $from = (int) $row->staff_user_id;
                $to = $map[$from] ?? null;
                if ($to === null || $to === $from) {
                    continue;
                }
                DB::table($table)->where('id', $row->id)->update(['staff_user_id' => $to]);
            }
        }
    }

    private function relinkSpatieRoles(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->moveGuardToWeb('permissions');
        $this->moveGuardToWeb('roles');

        $map = $this->staffIdToUserId();
        $this->remapMorphPivot('model_has_roles', 'role_id', $map);
        $this->remapMorphPivot('model_has_permissions', 'permission_id', $map);
    }

    private function moveGuardToWeb(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $staffRows = DB::table($table)->where('guard_name', 'staff')->orderBy('id')->get();
        foreach ($staffRows as $row) {
            $web = DB::table($table)->where('name', $row->name)->where('guard_name', 'web')->first();
            if ($web === null) {
                DB::table($table)->where('id', $row->id)->update(['guard_name' => 'web']);

                continue;
            }

            if ($table === 'roles') {
                $this->repointPivot('role_has_permissions', 'role_id', (int) $row->id, (int) $web->id);
                $this->repointPivot('model_has_roles', 'role_id', (int) $row->id, (int) $web->id);
            }

            if ($table === 'permissions') {
                $this->repointPivot('role_has_permissions', 'permission_id', (int) $row->id, (int) $web->id);
                $this->repointPivot('model_has_permissions', 'permission_id', (int) $row->id, (int) $web->id);
            }

            DB::table($table)->where('id', $row->id)->delete();
        }
    }

    private function repointPivot(string $table, string $key, int $from, int $to): void
    {
        if (! Schema::hasTable($table) || $from === $to) {
            return;
        }

        $rows = DB::table($table)->where($key, $from)->get();
        foreach ($rows as $row) {
            $query = DB::table($table)->where($key, $to);
            foreach ((array) $row as $column => $value) {
                if ($column === $key) {
                    continue;
                }
                $query->where($column, $value);
            }
            if ($query->exists()) {
                DB::table($table)->where($key, $from)->where(function ($inner) use ($row, $key): void {
                    foreach ((array) $row as $column => $value) {
                        if ($column === $key) {
                            continue;
                        }
                        $inner->where($column, $value);
                    }
                })->delete();

                continue;
            }

            DB::table($table)->where($key, $from)->where(function ($inner) use ($row, $key): void {
                foreach ((array) $row as $column => $value) {
                    if ($column === $key) {
                        continue;
                    }
                    $inner->where($column, $value);
                }
            })->update([$key => $to]);
        }
    }

    /**
     * @param  array<int, int>  $staffToUser
     */
    private function remapMorphPivot(string $table, string $key, array $staffToUser): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->where('model_type', self::STAFF_MODEL)->get();
        foreach ($rows as $row) {
            $userId = $staffToUser[(int) $row->model_id]
                ?? (int) (DB::table('users')->where('id', $row->model_id)->value('id') ?? 0);

            if ($userId === 0) {
                DB::table($table)
                    ->where($key, $row->{$key})
                    ->where('model_type', self::STAFF_MODEL)
                    ->where('model_id', $row->model_id)
                    ->delete();

                continue;
            }

            $already = DB::table($table)
                ->where($key, $row->{$key})
                ->where('model_type', self::USER_MODEL)
                ->where('model_id', $userId)
                ->exists();

            if ($already) {
                DB::table($table)
                    ->where($key, $row->{$key})
                    ->where('model_type', self::STAFF_MODEL)
                    ->where('model_id', $row->model_id)
                    ->delete();

                continue;
            }

            DB::table($table)
                ->where($key, $row->{$key})
                ->where('model_type', self::STAFF_MODEL)
                ->where('model_id', $row->model_id)
                ->update([
                    'model_type' => self::USER_MODEL,
                    'model_id' => $userId,
                ]);
        }
    }

    private function ensureCustomerUserIdUnique(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasColumn('customers', 'user_id')) {
            return;
        }

        if ($this->hasUniqueIndex('customers', 'user_id')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique('user_id');
        });
    }

    private function hasUniqueIndex(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            $columns = $index['columns'] ?? [];
            $unique = (bool) ($index['unique'] ?? false);
            if ($unique && $columns === [$column]) {
                return true;
            }
        }

        return false;
    }

    private function retargetStaffUserForeignKeys(): void
    {
        foreach (['tickets', 'customer_credit_entries'] as $table) {
            $this->retargetForeign($table, 'staff_user_id', 'users');
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'user_id')) {
            $this->retargetForeign('customers', 'user_id', 'users', cascade: true);
        }
    }

    private function retargetForeign(string $table, string $column, string $references, bool $cascade = false): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $current = $this->foreignKeyOn($table, $column);
        if ($current !== null && $current['foreign_table'] === $references) {
            return;
        }

        $this->dropForeignOn($table, $column);

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $cascade): void {
                $foreign = $blueprint->foreign($column)->references('id')->on($references);
                if ($cascade) {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
        } catch (Throwable) {
            if (! $this->isSqlite()) {
                throw new RuntimeException("Unable to retarget {$table}.{$column} to {$references}.");
            }
        }
    }

    /**
     * @return array{name: string|null, columns: list<string>, foreign_table: string}|null
     */
    private function foreignKeyOn(string $table, string $column): ?array
    {
        foreach (Schema::getForeignKeys($table) as $foreign) {
            if (($foreign['columns'] ?? []) === [$column]) {
                return $foreign;
            }
        }

        return null;
    }

    private function dropForeignOn(string $table, string $column): void
    {
        $foreign = $this->foreignKeyOn($table, $column);
        if ($foreign === null) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $foreign): void {
                if (is_string($foreign['name']) && $foreign['name'] !== '') {
                    $blueprint->dropForeign($foreign['name']);
                } else {
                    $blueprint->dropForeign([$column]);
                }
            });
        } catch (Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropForeign([$column]);
                });
            } catch (Throwable) {
                // SQLite rebuilds the table when columns are provided; older drivers may no-op.
            }
        }
    }

    private function dropCustomerAuthColumns(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $drop = array_values(array_filter([
            Schema::hasColumn('customers', 'password') ? 'password' : null,
            Schema::hasColumn('customers', 'remember_token') ? 'remember_token' : null,
            Schema::hasColumn('customers', 'email_verified_at') ? 'email_verified_at' : null,
            Schema::hasColumn('customers', 'anonymized_at') ? 'anonymized_at' : null,
            Schema::hasColumn('customers', 'deletion_requested_at') ? 'deletion_requested_at' : null,
        ]));

        if ($drop === []) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) use ($drop): void {
            $table->dropColumn($drop);
        });
    }

    private function assertDestinationReady(): void
    {
        if (! Schema::hasTable('users')) {
            throw new RuntimeException('Unified auth migration did not create the users table.');
        }

        if (Schema::hasTable('staff_users')) {
            $missing = DB::table('staff_users')
                ->whereNotIn('email', DB::table('users')->select('email'))
                ->count();
            if ($missing > 0) {
                throw new RuntimeException('Unified auth migration is missing staff identities on users.');
            }
        }

        if (Schema::hasTable('customers')) {
            $orphans = DB::table('customers')->whereNull('user_id')->count();
            if ($orphans > 0) {
                throw new RuntimeException('Unified auth migration left customer profiles without user_id.');
            }
        }

        if (Schema::hasTable('model_has_roles')) {
            $leftover = DB::table('model_has_roles')->where('model_type', self::STAFF_MODEL)->count();
            if ($leftover > 0) {
                throw new RuntimeException('Unified auth migration left StaffUser role morphs in place.');
            }
        }
    }

    private function invalidateLegacyCredentials(): void
    {
        DB::table('users')->update(['remember_token' => null]);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->delete();
        }

        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->delete();
        }

        if (Schema::hasTable('customer_password_reset_tokens')) {
            DB::table('customer_password_reset_tokens')->delete();
        }
    }

    private function dropSourceIdentityTables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('staff_users');
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::enableForeignKeyConstraints();
    }

    private function withDataTransaction(callable $callback): void
    {
        DB::transaction($callback);
    }

    private function resetSequence(string $table): void
    {
        $max = (int) (DB::table($table)->max('id') ?? 0);
        $driver = $this->driver();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE '.$table.' AUTO_INCREMENT = '.($max + 1));

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), GREATEST({$max}, 1))");

            return;
        }

        if ($driver === 'sqlite') {
            try {
                if (! Schema::hasTable('sqlite_sequence')) {
                    return;
                }
                $exists = DB::table('sqlite_sequence')->where('name', $table)->exists();
                if ($exists) {
                    DB::table('sqlite_sequence')->where('name', $table)->update(['seq' => $max]);
                } elseif ($max > 0) {
                    DB::table('sqlite_sequence')->insert(['name' => $table, 'seq' => $max]);
                }
            } catch (Throwable) {
                // sqlite_sequence is internal and not always writable the same way across builds.
            }
        }
    }

    private function isSqlite(): bool
    {
        return $this->driver() === 'sqlite';
    }

    private function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
