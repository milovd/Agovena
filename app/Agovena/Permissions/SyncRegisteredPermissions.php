<?php

declare(strict_types=1);

namespace App\Agovena\Permissions;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class SyncRegisteredPermissions
{
    private const FINGERPRINT_CACHE_KEY = 'agovena.registered_permissions.fingerprint';

    public function __construct(private readonly AdminRegistrar $admin) {}

    public function __invoke(bool $force = false): void
    {
        /** @var InMemoryAdminRegistrar $admin */
        $admin = $this->admin;
        $names = array_keys($admin->permissions());
        sort($names);
        $fingerprint = hash('sha256', implode("\0", $names));

        $owner = Role::findOrCreate('owner', User::GUARD);
        $ownerOutOfDate = $owner->permissions()->count() !== count($names);

        if (! $force && ! $ownerOutOfDate && Cache::get(self::FINGERPRINT_CACHE_KEY) === $fingerprint) {
            return;
        }

        foreach ($names as $name) {
            Permission::findOrCreate($name, User::GUARD);
        }

        $owner->syncPermissions($names);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forever(self::FINGERPRINT_CACHE_KEY, $fingerprint);
    }
}
