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
            $lockedUsers = $this->lockUsersForRoleChange($actor, $target);
            $lockedActor = $lockedUsers->firstWhere('id', $actor->getKey());
            $lockedTarget = $lockedUsers->firstWhere('id', $target->getKey());

            if (! $lockedActor instanceof User || ! $lockedTarget instanceof User) {
                throw ValidationException::withMessages([
                    $field => __('admin.customers.role_not_assignable'),
                ]);
            }

            $roleIdsByUser = DB::table(config('permission.table_names.model_has_roles'))
                ->where('model_type', $actor->getMorphClass())
                ->whereIn(config('permission.column_names.model_morph_key'), $lockedUsers->pluck('id')->all())
                ->orderBy('role_id')
                ->lockForUpdate()
                ->get()
                ->groupBy(config('permission.column_names.model_morph_key'));
            $assignedRoleIds = $roleIdsByUser->flatten()->pluck('role_id')->unique();
            $roles = Role::query()
                ->where('guard_name', User::GUARD)
                ->where(function ($query) use ($assignedRoleIds, $roleNames): void {
                    $query->whereKey($assignedRoleIds->all())->orWhereIn('name', $roleNames);
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($roles as $role) {
                $role->setRelation('permissions', $role->permissions()->lockForUpdate()->get());
            }
            foreach ($lockedUsers as $user) {
                $ids = $roleIdsByUser->get($user->getKey(), collect())->pluck('role_id');
                $user->setRelation('roles', $roles->filter(
                    fn (Role $role): bool => $ids->contains($role->getKey()),
                )->values());
            }
            $lockedActor->setRelation('permissions', $lockedActor->permissions()->lockForUpdate()->get());
            $rolesByName = $roles->keyBy('name');
            $ownerCount = $lockedUsers->filter(fn (User $user): bool => $user->hasRole('owner', User::GUARD))->count();
            $this->assertCanChangeRoles($lockedActor, $lockedTarget, $rolesByName, $roleNames, $field, $ownerCount);
            $lockedTarget->syncRoles(collect($roleNames)->map(fn (string $name): Role => $rolesByName->get($name))->all());

            return $lockedTarget->fresh(['roles']);
        });
    }

    /**
     * @param  Collection<string, Role>  $roles
     * @param  list<string>  $roleNames
     */
    private function assertCanChangeRoles(User $actor, User $target, Collection $roles, array $roleNames, string $field, int $ownerCount): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                $field => __('admin.customers.cannot_change_own_roles'),
            ]);
        }

        $target->loadMissing('roles');
        $currentRoleNames = $target->getRoleNames()->all();

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
                throw ValidationException::withMessages([
                    $field => __('admin.customers.role_not_assignable'),
                ]);
            }

            if (! $this->canGrantRole($actor, $role) && ! in_array($roleName, $currentRoleNames, true)) {
                throw ValidationException::withMessages([
                    $field => __('admin.customers.role_not_assignable'),
                ]);
            }
        }

        if ($targetIsOwner && ! in_array('owner', $roleNames, true)) {
            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    $field => __('admin.customers.last_owner'),
                ]);
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function lockUsersForRoleChange(User $actor, User $target): Collection
    {
        $ids = User::query()
            ->whereHas('roles', fn ($query) => $query
                ->where('guard_name', User::GUARD)
                ->where('name', 'owner'))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->push($actor->getKey())
            ->push($target->getKey())
            ->unique()
            ->sort()
            ->values();

        return User::query()
            ->whereKey($ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function canManageRole(User $actor, Role $role): bool
    {
        $actor->loadMissing('roles');

        if ($role->name === 'owner' || $actor->roles->contains($role)) {
            return false;
        }

        return $this->canGrantRole($actor, $role);
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
            if (! $permission instanceof Permission || ! $this->hasEffectivePermission($actor, $permission->name)) {
                return false;
            }
        }

        return true;
    }

    public function hasEffectivePermission(User $actor, string $ability): bool
    {
        $actor->loadMissing(['roles.permissions', 'permissions']);

        if ($actor->hasRole('owner', User::GUARD) || $actor->permissions->contains('name', $ability)) {
            return true;
        }

        foreach ($actor->roles as $role) {
            if ($role instanceof Role && $role->permissions->contains('name', $ability)) {
                return true;
            }
        }

        return false;
    }
}
