<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AdminRoleAssignmentPolicy
{
    /**
     * @return Collection<int, Role>
     */
    public function grantableRoles(User $actor, ?User $target = null): Collection
    {
        if ($target !== null) {
            $target->load('roles');
        }
        $currentRoleNames = $target?->getRoleNames()->all() ?? [];

        return Role::query()
            ->where('guard_name', User::GUARD)
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role): bool => in_array($role->name, $currentRoleNames, true)
                || $this->canGrantRole($actor, $role))
            ->values();
    }

    public function assertCanGrantRole(User $actor, Role $role, string $field): void
    {
        if ($this->canGrantRole($actor, $role)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => __('admin.customers.role_not_assignable'),
        ]);
    }

    /**
     * @param  list<string>  $roleNames
     */
    public function syncRoles(User $actor, User $target, array $roleNames, string $field): User
    {
        return DB::transaction(function () use ($actor, $target, $roleNames, $field): User {
            $this->lockOwnerUsers();
            $lockedTarget = User::query()->whereKey($target->getKey())->firstOrFail();
            $this->assertCanChangeRoles($actor, $lockedTarget, $roleNames, $field);
            $lockedTarget->syncRoles($roleNames);

            return $lockedTarget->fresh(['roles']);
        });
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function assertCanChangeRoles(User $actor, User $target, array $roleNames, string $field): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                $field => __('admin.customers.cannot_change_own_roles'),
            ]);
        }

        $target->load('roles');
        $currentRoleNames = $target->getRoleNames()->all();
        $roles = Role::query()
            ->where('guard_name', User::GUARD)
            ->whereIn('name', $roleNames)
            ->with('permissions')
            ->get()
            ->keyBy('name');

        $targetIsOwner = in_array('owner', $currentRoleNames, true);
        $actorIsOwner = $actor->hasRole('owner', User::GUARD);

        if ($targetIsOwner && ! $actorIsOwner) {
            throw ValidationException::withMessages([
                $field => __('admin.customers.cannot_change_owner'),
            ]);
        }

        foreach ($roleNames as $roleName) {
            $role = $roles->get($roleName);
            if (! $role instanceof Role) {
                continue;
            }

            if (! $this->canGrantRole($actor, $role) && ! in_array($roleName, $currentRoleNames, true)) {
                throw ValidationException::withMessages([
                    $field => __('admin.customers.role_not_assignable'),
                ]);
            }
        }

        if ($targetIsOwner && ! in_array('owner', $roleNames, true)) {
            $ownerCount = User::query()
                ->whereHas('roles', fn ($query) => $query
                    ->where('guard_name', User::GUARD)
                    ->where('name', 'owner'))
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    $field => __('admin.customers.last_owner'),
                ]);
            }
        }
    }

    private function lockOwnerUsers(): void
    {
        User::query()
            ->whereHas('roles', fn ($query) => $query
                ->where('guard_name', User::GUARD)
                ->where('name', 'owner'))
            ->orderBy('id')
            ->lockForUpdate()
            ->select('users.id')
            ->get();
    }

    public function canGrantRole(User $actor, Role $role): bool
    {
        if ($role->name === 'owner') {
            return $actor->hasRole('owner', User::GUARD);
        }

        if ($actor->hasRole('owner', User::GUARD)) {
            return true;
        }

        $role->loadMissing('permissions');

        foreach ($role->permissions as $permission) {
            if (! $permission instanceof Permission || ! $actor->can($permission->name)) {
                return false;
            }
        }

        return true;
    }
}
