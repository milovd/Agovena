<?php

use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\StaffUser;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('owner can list and create users with a role', function () {
    $staff = $this->createStaff();

    Role::findOrCreate('manager', 'staff');

    $this->actingAs($staff, 'staff')
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee(__('admin.users.title'), false);

    Livewire::actingAs($staff, 'staff')
        ->test(UsersIndex::class)
        ->call('create')
        ->set('name', 'Ada Admin')
        ->set('email', 'ada@agovena.test')
        ->set('password', 'ChangeMe-LocalOnly-1')
        ->set('role', 'manager')
        ->call('save')
        ->assertHasNoErrors();

    $created = StaffUser::query()->where('email', 'ada@agovena.test')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('manager'))->toBeTrue();
});

test('owner can create a role with permissions', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff, 'staff')
        ->test(RolesIndex::class)
        ->call('create')
        ->set('name', 'catalog_editor')
        ->set('selectedPermissions', ['products.view', 'products.update'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::query()->where('guard_name', 'staff')->where('name', 'catalog_editor')->first();
    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('products.view'))->toBeTrue()
        ->and($role->hasPermissionTo('products.update'))->toBeTrue()
        ->and($role->hasPermissionTo('orders.view'))->toBeFalse();
});

test('owner role cannot be deleted', function () {
    $staff = $this->createStaff();
    $owner = Role::findOrCreate('owner', 'staff');

    Livewire::actingAs($staff, 'staff')
        ->test(RolesIndex::class)
        ->call('delete', $owner->id)
        ->assertOk();

    expect(Role::query()->where('guard_name', 'staff')->where('name', 'owner')->exists())->toBeTrue();
});

test('staff without permission cannot open users admin', function () {
    $staff = $this->createStaff([], ['dashboard.view']);

    $this->actingAs($staff, 'staff')
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('staff without permission cannot open roles admin', function () {
    $staff = $this->createStaff([], ['dashboard.view']);

    $this->actingAs($staff, 'staff')
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});
