<?php

declare(strict_types=1);

namespace App\Agovena\Staff;

use App\Agovena\Installation\InstallationException;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

final class CreateOwnerStaff
{
    public function __construct(private readonly SyncRegisteredPermissions $sync) {}

    /**
     * Create or update the owner staff user and sync the owner role permissions.
     *
     * @param  bool  $refuseIfOwnerExists  When true (installer), refuse if another owner email already exists.
     */
    public function __invoke(
        string $name,
        string $email,
        string $password,
        bool $refuseIfOwnerExists = false,
    ): StaffUser {
        ($this->sync)(force: true);

        $email = strtolower(trim($email));

        if ($refuseIfOwnerExists) {
            $existingOwner = StaffUser::query()
                ->whereHas('roles', static fn ($q) => $q->where('name', 'owner')->where('guard_name', 'staff'))
                ->where('email', '!=', $email)
                ->exists();

            if ($existingOwner) {
                throw InstallationException::ownerAlreadyExists();
            }
        }

        $user = StaffUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ],
        );

        Role::findOrCreate('owner', 'staff');
        $user->syncRoles(['owner']);

        return $user;
    }
}
