<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRoleAssignmentPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('rechecks the actor roles from the database before changing another user roles', function (): void {
    $actor = $this->createStaff();
    $target = User::factory()->create();
    $limitedRole = Role::findOrCreate('staff_limited', User::GUARD);
    $limitedRole->syncPermissions([Permission::findOrCreate('products.view', User::GUARD)]);
    $actor->load('roles');

    DB::table('model_has_roles')
        ->where('model_type', User::class)
        ->where('model_id', $actor->id)
        ->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(User::query()->findOrFail($actor->id)->hasRole('owner'))->toBeFalse();

    expect(fn () => app(AdminRoleAssignmentPolicy::class)->syncRoles(
        $actor,
        $target,
        ['staff_limited'],
        'selectedRoles',
    ))->toThrow(ValidationException::class);

    expect($target->fresh()->roles)->toHaveCount(0)
        ->and($target->fresh()->hasRole('staff_limited'))->toBeFalse();
});
