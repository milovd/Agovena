<?php

declare(strict_types=1);

namespace App\Agovena\Permissions;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class SyncRegisteredPermissions
{
    public function __construct(private readonly AdminRegistrar $admin) {}

    public function __invoke(): void
    {
        /** @var InMemoryAdminRegistrar $admin */
        $admin = $this->admin;
        $names = [];

        foreach ($admin->permissions() as $name => $label) {
            Permission::findOrCreate($name, 'staff');
            $names[] = $name;
        }

        $owner = Role::findOrCreate('owner', 'staff');
        $owner->syncPermissions($names);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
