<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Agovena\Auth\TotpTwoFactor;
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
    protected function createStaff(array $attributes = [], ?array $permissions = null, bool $withTwoFactor = true): User
    {
        app(SyncRegisteredPermissions::class)();

        if (app(InstallationState::class)->notInstalled()) {
            app(InstallationState::class)->markInstalled();
        }

        $user = User::factory()->create($attributes);

        if ($permissions === null) {
            $user->assignRole('owner');
        } else {
            $role = Role::findOrCreate('staff_limited', User::GUARD);
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, User::GUARD);
            }
            $role->syncPermissions($permissions);
            $user->syncRoles([$role]);
        }

        if ($withTwoFactor) {
            $user->forceFill([
                'two_factor_secret' => app(TotpTwoFactor::class)->generateSecret(),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $user;
    }
}
