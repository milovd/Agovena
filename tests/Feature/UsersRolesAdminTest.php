<?php

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Livewire\Admin\Customers\Index as CustomersIndex;
use App\Livewire\Admin\Customers\Show as CustomersShow;
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Models\Customer;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('owner can list and create users with a role from customers admin', function () {
    $staff = $this->createStaff();

    Role::findOrCreate('manager', 'web');

    $this->actingAs($staff)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertSee(__('admin.customers.title'), false)
        ->assertSee(__('admin.customers.add_user'), false);

    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(CustomersIndex::class)
        ->call('createUser')
        ->set('userName', 'Ada Admin')
        ->set('userEmail', 'ada@agovena.test')
        ->set('userPassword', 'ChangeMe-LocalOnly-1')
        ->set('userRole', 'manager')
        ->call('saveUser')
        ->assertHasNoErrors();

    $created = User::query()->where('email', 'ada@agovena.test')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('manager'))->toBeTrue();
});

test('owner can assign roles to a user from the customer detail', function () {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create([
        'name' => 'Role Customer',
        'email' => 'role-customer@agovena.test',
    ]);
    $manager = Role::findOrCreate('manager', User::GUARD);

    $this->actingAs($staff)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertSee(__('admin.customers.save_roles'), false)
        ->assertDontSee(route('admin.users.index'), false);

    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(CustomersShow::class, ['customer' => $customer])
        ->set('selectedRoles', [$manager->name])
        ->call('saveRoles')
        ->assertHasNoErrors();

    expect($customer->user?->fresh()->hasRole('manager'))->toBeTrue();
});

test('customer role editor requires user update permission', function () {
    $staff = $this->createStaff([], ['customers.view']);
    $customer = Customer::factory()->create();

    $this->actingAs($staff)
        ->get(route('admin.customers.show', $customer))
        ->assertOk()
        ->assertDontSee(__('admin.customers.save_roles'), false);

    Livewire::actingAs($staff)
        ->test(CustomersShow::class, ['customer' => $customer])
        ->set('selectedRoles', ['owner'])
        ->call('saveRoles')
        ->assertForbidden();
});

test('users admin tab is removed but the legacy url redirects to customers', function () {
    $staff = $this->createStaff();

    expect(collect(app(AdminRegistrar::class)->navigationItems())->pluck('id'))
        ->not->toContain('users');

    $this->actingAs($staff)
        ->get(route('admin.users.index'))
        ->assertRedirect(route('admin.customers.index'));
});

test('legacy users view permission preserves access to the customers admin', function () {
    $staff = $this->createStaff([], ['users.view']);

    $this->actingAs($staff)
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertSee(__('admin.nav.customers'), false);

    expect($staff->can('customers.view'))->toBeTrue();
});

test('owner can create a role with permissions', function () {
    $staff = $this->createStaff();

    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(RolesIndex::class)
        ->call('create')
        ->set('name', 'catalog_editor')
        ->set('selectedPermissions', ['products.view', 'products.update'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::query()->where('guard_name', 'web')->where('name', 'catalog_editor')->first();
    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo('products.view'))->toBeTrue()
        ->and($role->hasPermissionTo('products.update'))->toBeTrue()
        ->and($role->hasPermissionTo('orders.view'))->toBeFalse();
});

test('limited staff cannot assign the owner role to a new customer account', function () {
    $staff = $this->createStaff([], ['customers.view', 'users.create']);
    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(CustomersIndex::class)
        ->call('createUser')
        ->set('userName', 'Blocked Owner')
        ->set('userEmail', 'blocked-owner@agovena.test')
        ->set('userPassword', 'ChangeMe-LocalOnly-1')
        ->set('userRole', 'owner')
        ->call('saveUser')
        ->assertHasErrors(['userRole']);

    expect(User::query()->where('email', 'blocked-owner@agovena.test')->exists())->toBeFalse();
});

test('limited staff cannot assign a role with permissions they do not have', function () {
    $staff = $this->createStaff([], ['customers.view', 'users.create']);
    $permission = Permission::findOrCreate('settings.update', User::GUARD);
    $role = Role::findOrCreate('settings_admin', User::GUARD);
    $role->syncPermissions([$permission]);
    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(CustomersIndex::class)
        ->call('createUser')
        ->set('userName', 'Blocked Settings Admin')
        ->set('userEmail', 'blocked-settings-admin@agovena.test')
        ->set('userPassword', 'ChangeMe-LocalOnly-1')
        ->set('userRole', $role->name)
        ->call('saveUser')
        ->assertHasErrors(['userRole']);

    expect(User::query()->where('email', 'blocked-settings-admin@agovena.test')->exists())->toBeFalse();
});

test('staff cannot change their own customer roles', function () {
    $staff = $this->createStaff([], ['customers.view', 'users.update']);
    $customer = $staff->customer()->firstOrFail();
    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(CustomersShow::class, ['customer' => $customer])
        ->set('selectedRoles', [])
        ->call('saveRoles')
        ->assertHasErrors(['selectedRoles']);

    expect($staff->fresh()->hasRole('staff_limited'))->toBeTrue();
});

test('owner cannot remove the owner role from their own account', function () {
    $staff = $this->createStaff();
    $customer = $staff->customer()->firstOrFail();
    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(CustomersShow::class, ['customer' => $customer])
        ->set('selectedRoles', [])
        ->call('saveRoles')
        ->assertHasErrors(['selectedRoles']);

    expect($staff->fresh()->hasRole('owner'))->toBeTrue();
});

test('owner role cannot be deleted', function () {
    $staff = $this->createStaff();
    $owner = Role::findOrCreate('owner', 'web');

    session([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(RolesIndex::class)
        ->call('delete', $owner->id)
        ->assertOk();

    expect(Role::query()->where('guard_name', 'web')->where('name', 'owner')->exists())->toBeTrue();
});

test('staff without customer permission cannot open customers admin', function () {
    $staff = $this->createStaff([], ['dashboard.view']);

    $this->actingAs($staff)
        ->get(route('admin.customers.index'))
        ->assertForbidden();
});

test('staff without permission cannot open roles admin', function () {
    $staff = $this->createStaff([], ['dashboard.view']);

    $this->actingAs($staff)
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});
