<?php

use App\Livewire\Admin\Staff\Index;
use App\Models\StaffUser;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('owner can list and create staff users', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff, 'staff')
        ->get(route('admin.staff.index'))
        ->assertOk()
        ->assertSee('Staff', false);

    Livewire::actingAs($staff, 'staff')
        ->test(Index::class)
        ->call('create')
        ->set('name', 'Ada Admin')
        ->set('email', 'ada@agovena.test')
        ->set('password', 'ChangeMe-LocalOnly-1')
        ->set('role', 'owner')
        ->call('save')
        ->assertHasNoErrors();

    $created = StaffUser::query()->where('email', 'ada@agovena.test')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('owner'))->toBeTrue();
});

test('staff without permission cannot open staff admin', function () {
    $staff = $this->createStaff([], ['dashboard.view']);

    $this->actingAs($staff, 'staff')
        ->get(route('admin.staff.index'))
        ->assertForbidden();
});
