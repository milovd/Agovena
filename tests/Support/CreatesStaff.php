<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Agovena\Installation\InstallationState;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait CreatesStaff
{
    /**
     * @param  list<string>|null  $permissions
     */
    protected function createStaff(array $attributes = [], ?array $permissions = null): User
    {
        app(SyncRegisteredPermissions::class)();

        if (app(InstallationState::class)->notInstalled()) {
            app(InstallationState::class)->markInstalled();
        }

        $user = User::factory()->create($attributes);

        if ($permissions === null) {
            $user->assignRole('owner');

            return $user;
        }

        $role = Role::findOrCreate('staff_limited', User::GUARD);
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, User::GUARD);
        }
        $role->syncPermissions($permissions);
        $user->syncRoles([$role]);

        return $user;
    }
}
