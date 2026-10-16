<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRoleAssignmentPolicy;
use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('limited staff cannot add an unheld permission to a manageable role', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.update']);
    $role = Role::findOrCreate('manageable_role', User::GUARD);
    $role->syncPermissions(['roles.view']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)
        ->test(RolesIndex::class)
        ->set('editingId', $role->id)
        ->set('name', $role->name)
        ->set('selectedPermissions', ['roles.view', 'roles.update', 'settings.update'])
        ->call('save')
        ->assertHasErrors(['selectedPermissions']);

    expect($role->fresh()->hasPermissionTo('settings.update'))->toBeFalse();
});

test('staff cannot mutate permissions or actions of a role assigned to themselves', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.update', 'roles.delete']);
    $role = Role::findByName('staff_limited', User::GUARD);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('edit', $role->id)
        ->assertForbidden();
    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->set('showForm', true)
        ->set('editingId', $role->id)
        ->set('name', $role->name)
        ->set('selectedPermissions', ['roles.view'])
        ->assertDontSee('wire:submit="save"', false)
        ->call('save')
        ->assertForbidden();
    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('delete', $role->id)
        ->assertForbidden();

    expect($role->fresh()->hasPermissionTo('roles.update'))->toBeTrue();
});

test('limited staff cannot edit a role whose existing permissions exceed their own', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.update']);
    $role = Role::findOrCreate('privileged_role', User::GUARD);
    $role->syncPermissions(['settings.update']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('edit', $role->id)
        ->assertForbidden();

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->set('editingId', $role->id)
        ->set('name', 'forged_role')
        ->set('selectedPermissions', ['roles.view'])
        ->call('save')
        ->assertForbidden();

    expect($role->fresh()->name)->toBe('privileged_role')
        ->and($role->fresh()->hasPermissionTo('settings.update'))->toBeTrue();
});

test('limited staff cannot delete an unused role with unheld permissions', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.delete']);
    $role = Role::findOrCreate('unused_privileged_role', User::GUARD);
    $role->syncPermissions(['settings.update']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('delete', $role->id)
        ->assertForbidden();

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

test('view-only staff cannot reveal a role editing form by forging Livewire state', function (): void {
    $viewer = $this->createStaff(permissions: ['roles.view']);

    Livewire::actingAs($viewer)->test(RolesIndex::class)
        ->set('showForm', true)
        ->assertDontSee('wire:submit="save"', false)
        ->assertDontSee('wire:click="create"', false);
});

test('limited role creators only see and can grant permissions they hold', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.create']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('create')
        ->assertSee('value="roles.view"', false)
        ->assertDontSee('value="settings.update"', false)
        ->set('name', 'ungrantable_role')
        ->set('selectedPermissions', ['settings.update'])
        ->call('save')
        ->assertHasErrors(['selectedPermissions']);

    expect(Role::query()->where('name', 'ungrantable_role')->exists())->toBeFalse();
});

test('permissions revoked after opening the editor are denied on save', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.update', 'products.view']);
    $ownRole = Role::findByName('staff_limited', User::GUARD);
    $role = Role::findOrCreate('revocation_target', User::GUARD);
    $role->syncPermissions(['roles.view']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    $editor = Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('edit', $role->id);
    $ownRole->syncPermissions(['roles.view', 'roles.update']);

    $editor->set('name', 'revocation_target_renamed')
        ->set('selectedPermissions', ['products.view'])
        ->call('save')
        ->assertHasErrors(['selectedPermissions']);

    expect($role->fresh()->name)->toBe('revocation_target')
        ->and($role->fresh()->hasPermissionTo('roles.view'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('products.view'))->toBeFalse();
});

test('a revoked permission is not grantable from a stale registrar mapping', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.update', 'products.view']);
    $ownRole = Role::findByName('staff_limited', User::GUARD);
    $role = Role::findOrCreate('stale_mapping_target', User::GUARD);
    $role->syncPermissions(['roles.view']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    $editor = Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('edit', $role->id);
    $permission = Permission::findByName('products.view', User::GUARD);
    DB::table(config('permission.table_names.role_has_permissions'))
        ->where('role_id', $ownRole->id)
        ->where('permission_id', $permission->id)
        ->delete();

    $editor->set('selectedPermissions', ['products.view'])
        ->call('save')
        ->assertHasErrors(['selectedPermissions']);

    expect($role->fresh()->hasPermissionTo('products.view'))->toBeFalse();
});

test('role creation leaves no partial role when a later step fails', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.create', 'products.view']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    $dispatcher = Role::getEventDispatcher();
    Role::setEventDispatcher(clone $dispatcher);
    Role::created(function (Role $created): void {
        if ($created->name === 'atomic_created_role') {
            throw new RuntimeException('Simulated role creation failure');
        }
    });

    try {
        expect(fn () => Livewire::actingAs($staff)->test(RolesIndex::class)
            ->call('create')
            ->set('name', 'atomic_created_role')
            ->set('selectedPermissions', ['products.view'])
            ->call('save'))->toThrow(RuntimeException::class);
    } finally {
        Role::setEventDispatcher($dispatcher);
    }

    expect(Role::query()->where('name', 'atomic_created_role')->exists())->toBeFalse();
});

test('role name stays unchanged when a later update step fails', function (): void {
    $owner = $this->createStaff();
    $role = Role::findOrCreate('atomic_role', User::GUARD);
    $role->syncPermissions(['products.view']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $owner->id,
    ]);

    $dispatcher = Role::getEventDispatcher();
    Role::setEventDispatcher(clone $dispatcher);
    Role::updated(function (Role $updated) use ($role): void {
        if ($updated->is($role)) {
            throw new RuntimeException('Simulated role update failure');
        }
    });

    try {
        expect(fn () => Livewire::actingAs($owner)->test(RolesIndex::class)
            ->call('edit', $role->id)
            ->set('name', 'atomic_role_renamed')
            ->set('selectedPermissions', ['orders.view'])
            ->call('save'))->toThrow(RuntimeException::class);
    } finally {
        Role::setEventDispatcher($dispatcher);
    }

    expect($role->fresh()->name)->toBe('atomic_role')
        ->and($role->fresh()->hasPermissionTo('products.view'))->toBeTrue();
});

test('limited role managers do not see actions for roles beyond their permissions', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.update', 'roles.delete']);
    $ownRole = Role::findByName('staff_limited', User::GUARD);
    $manageable = Role::findOrCreate('manageable_role', User::GUARD);
    $manageable->syncPermissions(['roles.view']);
    $privileged = Role::findOrCreate('privileged_role', User::GUARD);
    $privileged->syncPermissions(['settings.update']);

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->assertSee('wire:click="edit('.$manageable->id.')"', false)
        ->assertDontSee('wire:click="edit('.$ownRole->id.')"', false)
        ->assertDontSee('wire:click="edit('.$privileged->id.')"', false)
        ->assertDontSee('wire:click="delete('.$privileged->id.')"', false);
});

test('limited managers retain role creation editing and deletion within their own permission subset', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'products.view']);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('create')
        ->set('name', 'limited_catalog_role')
        ->set('selectedPermissions', ['products.view'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::findByName('limited_catalog_role', User::GUARD);
    expect($role->hasPermissionTo('products.view'))->toBeTrue();

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('edit', $role->id)
        ->set('name', 'limited_catalog_editor')
        ->set('selectedPermissions', ['roles.view'])
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->name)->toBe('limited_catalog_editor')
        ->and($role->fresh()->hasPermissionTo('roles.view'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('products.view'))->toBeFalse();

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->call('delete', $role->id)
        ->assertOk();
    expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});

test('a revoked delete permission cannot remove an unassigned role from a stale request', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.delete']);
    $ownRole = Role::findByName('staff_limited', User::GUARD);
    $role = Role::findOrCreate('stale_delete_target', User::GUARD);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    $editor = Livewire::actingAs($staff)->test(RolesIndex::class);
    $permission = Permission::findByName('roles.delete', User::GUARD);
    DB::table(config('permission.table_names.role_has_permissions'))
        ->where('role_id', $ownRole->id)
        ->where('permission_id', $permission->id)
        ->delete();

    $editor->call('delete', $role->id)->assertForbidden();
    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

test('deletion confirmation describes the in-use guard in both locales', function (string $locale, string $expected): void {
    app()->setLocale($locale);
    $owner = $this->createStaff();
    $role = Role::findOrCreate('deletable_role', User::GUARD);

    Livewire::actingAs($owner)->test(RolesIndex::class)
        ->assertSee('wire:click="delete('.$role->id.')"', false)
        ->assertSee('wire:confirm="'.e(__('admin.roles.delete_confirm')).'"', false);

    expect(__('admin.roles.delete_confirm'))->toContain($expected);
})->with([
    ['en', 'Only roles without assigned users can be deleted'],
    ['nl', 'Alleen rollen zonder toegewezen gebruikers kunnen worden verwijderd'],
]);

test('viewer intro describes permission counts instead of unseen permission names', function (string $locale, string $expected): void {
    app()->setLocale($locale);
    $staff = $this->createStaff(permissions: ['roles.view']);

    Livewire::actingAs($staff)->test(RolesIndex::class)
        ->assertSee(__('admin.roles.lede_viewer'));

    expect(__('admin.roles.lede_viewer'))->toContain($expected);
})->with([
    ['en', 'permission counts'],
    ['nl', 'aantal rechten per rol'],
]);

test('owner role has no edit action and cannot be edited directly', function (): void {
    $owner = $this->createStaff();
    $role = Role::findByName('owner', User::GUARD);

    Livewire::actingAs($owner)->test(RolesIndex::class)
        ->assertDontSee('wire:click="edit('.$role->id.')"', false)
        ->call('edit', $role->id)
        ->assertForbidden();
});

test('role facts remain labelled and keyboard reachable on narrow screens for view-only staff', function (string $locale, string $systemLabel): void {
    app()->setLocale($locale);
    $viewer = $this->createStaff(permissions: ['roles.view']);

    Livewire::actingAs($viewer)->test(RolesIndex::class)
        ->assertSee('ag-table-wrap--mobile-cards', false)
        ->assertSee('role="region"', false)
        ->assertSee('tabindex="0"', false)
        ->assertSee('data-label="'.__('admin.roles.name').'"', false)
        ->assertSee('class="ag-table__primary"', false)
        ->assertSee('data-label="'.__('admin.roles.permissions').'"', false)
        ->assertSee('data-label="'.__('admin.roles.users_count').'"', false)
        ->assertSee($systemLabel)
        ->assertSee('ag-badge--muted', false)
        ->assertSee(__('admin.roles.lede_viewer'))
        ->assertDontSee(__('admin.roles.lede'))
        ->assertDontSee('admin.roles.system')
        ->assertDontSee('data-label="'.__('common.actions').'"', false);

    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML(Livewire::actingAs($viewer)->test(RolesIndex::class)->html(), LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    foreach ($xpath->query('//table[contains(@class, "ag-table--roles")]/tbody/tr') as $row) {
        expect($xpath->query('./td', $row)->length)->toBe(3);
    }
})->with([['en', 'System'], ['nl', 'Systeem']]);

test('an out-of-range roles page does not claim the role catalogue is empty', function (string $locale): void {
    app()->setLocale($locale);
    $viewer = $this->createStaff(permissions: ['roles.view']);

    Livewire::actingAs($viewer)->test(RolesIndex::class)
        ->set('paginators.page', 99)
        ->assertDontSee(__('admin.roles.empty'))
        ->assertSee(__('admin.roles.empty_page'))
        ->assertSee('wire:click="resetPage"', false)
        ->call('resetPage')
        ->assertSee('data-label="'.__('admin.roles.name').'"', false);
})->with(['en', 'nl']);

/** @return array{ArrayObject<int, array{sql: string, bindings: array}>, Closure(): void} */
function traceRoleLocks(): array
{
    $connection = DB::connection();
    $originalGrammar = $connection->getQueryGrammar();
    // SQLite drops FOR UPDATE; preserve the requested lock as an inert SQL comment.
    // MySQL must retain its own grammar so this test traces real locking reads.
    if ($connection->getDriverName() === 'sqlite') {
        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
        {
            protected function compileLock(Builder $query, $value)
            {
                return $value ? ' /* locking read */' : '';
            }
        });
    }

    $queries = new ArrayObject;
    $recording = new ArrayObject(['active' => true]);
    $minimumLevel = DB::transactionLevel() + 1;
    DB::listen(function ($query) use ($queries, $recording, $minimumLevel): void {
        if ($recording['active'] && DB::transactionLevel() >= $minimumLevel) {
            $queries->append(['sql' => $query->sql, 'bindings' => $query->bindings]);
        }
    });

    return [$queries, static function () use ($connection, $originalGrammar, $recording): void {
        $recording['active'] = false;
        $connection->setQueryGrammar($originalGrammar);
    }];
}

function tracedRoleTable(string $sql, string $table): bool
{
    return preg_match('/\bfrom\s+(["`])'.preg_quote($table, '/').'\1(?:\s|$)/i', $sql) === 1;
}

function tracedRoleColumnEqualsPlaceholder(string $sql, string $column): bool
{
    return preg_match('/(["`])'.preg_quote($column, '/').'\1\s*=\s*\?/', $sql) === 1;
}

function tracedRoleLock(string $sql): bool
{
    return str_contains($sql, '/* locking read */')
        || preg_match('/\sfor update\s*$/i', $sql) === 1;
}

test('role lock tracing keeps native MySQL query grammar and lock syntax', function (): void {
    $defaultConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql');
    try {
        [, $stopTracing] = traceRoleLocks();
        try {
            $query = DB::table('users')->where('name', 'owner')->lockForUpdate();
            $sql = $query->toSql();
            expect(DB::connection()->getQueryGrammar())->toBeInstanceOf(MySqlGrammar::class)
                ->and($sql)->toContain('`users`')->toContain('for update')
                ->and(tracedRoleTable($sql, 'users'))->toBeTrue()
                ->and(tracedRoleColumnEqualsPlaceholder($sql, 'name'))->toBeTrue()
                ->and(tracedRoleLock($sql))->toBeTrue()
                ->and(tracedRoleTable('select * from `users"', 'users'))->toBeFalse()
                ->and(tracedRoleColumnEqualsPlaceholder('where `name" = ?', 'name'))->toBeFalse();
        } finally {
            $stopTracing();
        }
    } finally {
        DB::setDefaultConnection($defaultConnection);
    }
});

test('role assignment locks candidate permissions before deciding grantability', function (): void {
    $staff = $this->createStaff(permissions: ['users.update', 'roles.view']);
    $target = User::factory()->create();
    $candidate = Role::findOrCreate('candidate_with_unheld_permission', User::GUARD);
    $candidate->syncPermissions(['settings.update']);

    [$queries, $stopTracing] = traceRoleLocks();
    try {
        expect(fn () => app(AdminRoleAssignmentPolicy::class)
            ->syncRoles($staff, $target, [$candidate->name], 'selectedRoles'))
            ->toThrow(ValidationException::class);
    } finally {
        $stopTracing();
    }

    $roleRead = collect($queries)->first(fn (array $query): bool => tracedRoleTable($query['sql'], 'roles')
        && in_array($candidate->name, $query['bindings'], true));
    $permissionRead = collect($queries)->first(fn (array $query): bool => str_contains($query['sql'], 'role_has_permissions')
        && in_array($candidate->id, $query['bindings'], true));
    expect(tracedRoleLock($roleRead['sql'] ?? ''))->toBeTrue()
        ->and(tracedRoleLock($permissionRead['sql'] ?? ''))->toBeTrue()
        ->and($target->fresh()->roles)->toHaveCount(0);
});

test('role assignment uses the locked role identity without a later name lookup', function (): void {
    $owner = $this->createStaff();
    $target = User::factory()->create();
    $role = Role::findOrCreate('locked_assignment_identity', User::GUARD);

    [$queries, $stopTracing] = traceRoleLocks();
    try {
        app(AdminRoleAssignmentPolicy::class)->syncRoles($owner, $target, [$role->name], 'selectedRoles');
    } finally {
        $stopTracing();
    }

    $unlockedNameLookups = collect($queries)->filter(fn (array $query): bool => tracedRoleTable($query['sql'], 'roles')
        && tracedRoleColumnEqualsPlaceholder($query['sql'], 'name')
        && in_array($role->name, $query['bindings'], true)
        && ! tracedRoleLock($query['sql']));
    expect($unlockedNameLookups)->toBeEmpty()
        ->and($target->fresh()->roles->pluck('name')->all())->toBe([$role->name]);
});

test('the last owner guard counts locked role assignments instead of an old snapshot', function (): void {
    $actor = $this->createStaff();
    $target = User::factory()->create();
    $target->assignRole('owner');

    [$queries, $stopTracing] = traceRoleLocks();
    try {
        app(AdminRoleAssignmentPolicy::class)->syncRoles($actor, $target, [], 'selectedRoles');
    } finally {
        $stopTracing();
    }

    $snapshotCounts = collect($queries)->filter(fn (array $query): bool => str_contains($query['sql'], 'count(*)')
        && tracedRoleTable($query['sql'], 'users')
        && tracedRoleColumnEqualsPlaceholder($query['sql'], 'name')
        && in_array('owner', $query['bindings'], true)
        && ! tracedRoleLock($query['sql']));
    expect($snapshotCounts)->toBeEmpty()
        ->and($target->fresh()->hasRole('owner'))->toBeFalse()
        ->and($actor->fresh()->hasRole('owner'))->toBeTrue();
});

test('role deletion locks assignment pivots before checking whether the role is unused', function (): void {
    $staff = $this->createStaff(permissions: ['roles.view', 'roles.delete']);
    $role = Role::findOrCreate('deletion_lock_target', User::GUARD);
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);

    [$queries, $stopTracing] = traceRoleLocks();
    try {
        Livewire::actingAs($staff)->test(RolesIndex::class)
            ->call('delete', $role->id)->assertOk();
    } finally {
        $stopTracing();
    }

    $pivotReads = collect($queries)->filter(fn (array $query): bool => tracedRoleTable($query['sql'], 'model_has_roles')
        && tracedRoleColumnEqualsPlaceholder($query['sql'], 'role_id')
        && str_starts_with($query['sql'], 'select')
        && in_array($role->id, $query['bindings'], true));
    expect($pivotReads)->not->toBeEmpty()
        ->and(tracedRoleLock($pivotReads->first()['sql']))->toBeTrue(json_encode($pivotReads->pluck('sql')->all()))
        ->and(Role::query()->whereKey($role->id)->exists())->toBeFalse();
});
