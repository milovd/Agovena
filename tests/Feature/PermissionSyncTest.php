<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin visit syncs newly registered permissions onto the owner role', function () {
    $staff = $this->createStaff();

    $owner = Role::findByName('owner', 'staff');
    $owner->revokePermissionTo('users.view');
    $owner->revokePermissionTo('roles.view');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    cache()->forget('agovena.registered_permissions.fingerprint');

    expect($staff->fresh()->can('users.view'))->toBeFalse();

    $this->actingAs($staff, 'staff')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.nav.users'), false)
        ->assertSee(__('admin.nav.roles'), false);

    expect($staff->fresh()->can('users.view'))->toBeTrue()
        ->and($staff->fresh()->can('roles.view'))->toBeTrue()
        ->and(Permission::findByName('users.view', 'staff'))->not->toBeNull();
});
