<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\StaffUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait CreatesStaff
{
    /**
     * @param  list<string>|null  $permissions
     */
    protected function createStaff(array $attributes = [], ?array $permissions = null): StaffUser
    {
        app(SyncRegisteredPermissions::class)();

        $user = StaffUser::factory()->create($attributes);

        if ($permissions === null) {
            $user->assignRole('owner');

            return $user;
        }

        $role = Role::findOrCreate('staff_limited', 'staff');
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'staff');
        }
        $role->syncPermissions($permissions);
        $user->assignRole($role);

        return $user;
    }
}
