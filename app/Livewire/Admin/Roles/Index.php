<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\AdminRoleAssignmentPolicy;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

final class Index extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    /** @var list<string> */
    public array $selectedPermissions = [];

    public function mount(SyncRegisteredPermissions $sync): void
    {
        $this->authorize('roles.view');
        $sync();
    }

    public function create(): void
    {
        $this->authorize('roles.create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $roleId, AdminRoleAssignmentPolicy $assignment): void
    {
        $this->authorize('roles.update');
        $role = Role::query()->where('guard_name', User::GUARD)->findOrFail($roleId);
        $actor = auth()->user();
        abort_unless($actor instanceof User && $assignment->canManageRole($actor, $role), 403);
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->values()->all();
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(AdminRegistrar $admin, SyncRegisteredPermissions $sync, AdminRoleAssignmentPolicy $assignment): void
    {
        if ($this->editingId === null) {
            $this->authorize('roles.create');
        } else {
            $this->authorize('roles.update');
            $role = Role::query()->where('guard_name', User::GUARD)->findOrFail($this->editingId);
            $actor = auth()->user();
            abort_unless($actor instanceof User && $assignment->canManageRole($actor, $role), 403);
        }

        if (! $this->requireRecentPassword('save')) {
            return;
        }

        $sync();

        $permissionNames = array_keys($admin->permissions());

        $data = $this->validate([
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', User::GUARD))
                    ->ignore($this->editingId),
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', Rule::in($permissionNames)],
        ]);

        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        DB::transaction(function () use ($actor, $assignment, $data): void {
            [$lockedActor, $lockedRoles] = $this->lockRoleMutationContext($actor, $this->editingId);

            if ($this->editingId === null) {
                abort_unless($assignment->hasEffectivePermission($lockedActor, 'roles.create'), 403);
                $this->assertGrantablePermissions($lockedActor, $data['selectedPermissions'] ?? [], $assignment);
                $role = Role::query()->create([
                    'name' => $data['name'],
                    'guard_name' => User::GUARD,
                ]);
            } else {
                $role = $lockedRoles->firstWhere('id', $this->editingId);
                abort_unless($role instanceof Role && $assignment->hasEffectivePermission($lockedActor, 'roles.update')
                    && $assignment->canManageRole($lockedActor, $role), 403);
                $this->assertGrantablePermissions($lockedActor, $data['selectedPermissions'] ?? [], $assignment);
                $role->update(['name' => $data['name']]);
            }

            $role->syncPermissions($data['selectedPermissions'] ?? []);
        });
        session()->flash('status', $this->editingId === null ? __('admin.roles.created') : __('admin.roles.updated'));

        $this->resetForm();
        $this->resetPage();
    }

    public function deleteRole(int $roleId, AdminRoleAssignmentPolicy $assignment): void
    {
        $this->authorize('roles.delete');
        $role = Role::query()->where('guard_name', User::GUARD)->findOrFail($roleId);
        $actor = auth()->user();
        abort_unless($actor instanceof User && ($role->name === 'owner'
            ? $actor->hasRole('owner', User::GUARD)
            : $assignment->canManageRole($actor, $role)), 403);

        if (! $this->requireRecentPassword('delete', ['roleId' => $roleId])) {
            return;
        }
        if ($role->name === 'owner') {
            session()->flash('error', __('admin.roles.cannot_delete_owner'));

            return;
        }

        $deleted = DB::transaction(function () use ($actor, $assignment, $roleId): bool {
            [$lockedActor, $lockedRoles] = $this->lockRoleMutationContext($actor, $roleId);
            $lockedRole = $lockedRoles->firstWhere('id', $roleId);
            abort_unless($lockedRole instanceof Role && $assignment->hasEffectivePermission($lockedActor, 'roles.delete')
                && $assignment->canManageRole($lockedActor, $lockedRole), 403);

            if (DB::table(config('permission.table_names.model_has_roles'))
                ->where('role_id', $lockedRole->getKey())
                ->lockForUpdate()
                ->first(['role_id']) !== null) {
                return false;
            }

            $lockedRole->delete();

            return true;
        });
        if (! $deleted) {
            session()->flash('error', __('admin.roles.in_use'));

            return;
        }

        session()->flash('status', __('admin.roles.deleted'));
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin, AdminRoleAssignmentPolicy $assignment)
    {
        $this->authorize('roles.view');
        $actor = auth()->user();
        $formRole = $this->editingId === null
            ? null
            : Role::query()->where('guard_name', User::GUARD)->with('permissions')->find($this->editingId);
        $canShowForm = $this->showForm && $actor instanceof User
            && ($this->editingId === null
                ? $actor->can('roles.create')
                : ($actor->can('roles.update') && $formRole !== null && $assignment->canManageRole($actor, $formRole)));
        $roles = Role::query()
            ->where('guard_name', User::GUARD)
            ->withCount('users')
            ->with('permissions')
            ->orderByRaw("CASE WHEN name = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(20);
        $manageableRoleIds = $actor instanceof User
            ? $roles->getCollection()
                ->filter(fn (Role $role): bool => $assignment->canManageRole($actor, $role))
                ->pluck('id')
                ->all()
            : [];

        return view('livewire.admin.roles.index', [
            'roles' => $roles,
            'manageableRoleIds' => $manageableRoleIds,
            'allPermissions' => $actor instanceof User
                ? array_filter(
                    $admin->permissions(),
                    fn ($label, $ability): bool => $actor->hasRole('owner', User::GUARD) || $actor->can($ability),
                    ARRAY_FILTER_USE_BOTH,
                )
                : [],
            'canShowForm' => $canShowForm,
        ])->layout('layouts.admin', [
            'title' => __('admin.roles.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    /**
     * @return array{User, Collection<int, Role>}
     */
    private function lockRoleMutationContext(User $actor, ?int $targetRoleId): array
    {
        $lockedActor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
        $assignedRoleIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', $lockedActor->getMorphClass())
            ->where(config('permission.column_names.model_morph_key'), $lockedActor->getKey())
            ->orderBy('role_id')
            ->lockForUpdate()
            ->pluck('role_id');
        $roleIds = clone $assignedRoleIds;
        if ($targetRoleId !== null) {
            $roleIds->push($targetRoleId);
        }
        $lockedRoles = Role::query()
            ->where('guard_name', User::GUARD)
            ->whereKey($roleIds->unique()->sort()->values()->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // Locking reads see committed grants even under repeatable-read isolation.
        foreach ($lockedRoles as $role) {
            $role->setRelation('permissions', $role->permissions()->lockForUpdate()->get());
        }
        $lockedActor->setRelation('roles', $lockedRoles
            ->filter(fn (Role $role): bool => $assignedRoleIds->contains($role->getKey()))
            ->values());
        $lockedActor->setRelation('permissions', $lockedActor->permissions()->lockForUpdate()->get());

        return [$lockedActor, $lockedRoles];
    }

    /** @param list<string> $permissions */
    private function assertGrantablePermissions(User $actor, array $permissions, AdminRoleAssignmentPolicy $assignment): void
    {
        foreach ($permissions as $permission) {
            if (! $assignment->hasEffectivePermission($actor, $permission)) {
                throw ValidationException::withMessages([
                    'selectedPermissions' => __('admin.customers.role_not_assignable'),
                ]);
            }
        }
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->selectedPermissions = [];
        $this->resetValidation();
    }
}
