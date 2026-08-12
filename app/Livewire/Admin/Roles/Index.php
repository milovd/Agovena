<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Roles;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

final class Index extends Component
{
    use AuthorizesRequests;
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

    public function edit(int $roleId): void
    {
        $this->authorize('roles.update');
        $role = Role::query()->where('guard_name', 'staff')->findOrFail($roleId);
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->values()->all();
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(AdminRegistrar $admin, SyncRegisteredPermissions $sync): void
    {
        if ($this->editingId === null) {
            $this->authorize('roles.create');
        } else {
            $this->authorize('roles.update');
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
                    ->where(fn ($query) => $query->where('guard_name', 'staff'))
                    ->ignore($this->editingId),
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', Rule::in($permissionNames)],
        ]);

        if ($this->editingId === null) {
            $role = Role::query()->create([
                'name' => $data['name'],
                'guard_name' => 'staff',
            ]);
            $role->syncPermissions($data['selectedPermissions'] ?? []);
            session()->flash('status', __('admin.roles.created'));
        } else {
            $role = Role::query()->where('guard_name', 'staff')->findOrFail($this->editingId);

            if ($role->name === 'owner') {
                if ($data['name'] !== 'owner') {
                    throw ValidationException::withMessages([
                        'name' => __('admin.roles.cannot_rename_owner'),
                    ]);
                }
                $sync();
                session()->flash('status', __('admin.roles.updated'));
            } else {
                $role->update(['name' => $data['name']]);
                $role->syncPermissions($data['selectedPermissions'] ?? []);
                session()->flash('status', __('admin.roles.updated'));
            }
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function delete(int $roleId): void
    {
        $this->authorize('roles.delete');
        $role = Role::query()->where('guard_name', 'staff')->findOrFail($roleId);

        if ($role->name === 'owner') {
            session()->flash('error', __('admin.roles.cannot_delete_owner'));

            return;
        }

        if ($role->users()->count() > 0) {
            session()->flash('error', __('admin.roles.in_use'));

            return;
        }

        $role->delete();
        session()->flash('status', __('admin.roles.deleted'));
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.roles.index', [
            'roles' => Role::query()
                ->where('guard_name', 'staff')
                ->withCount('users')
                ->with('permissions')
                ->orderByRaw("CASE WHEN name = 'owner' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->paginate(20),
            'allPermissions' => $admin->permissions(),
            'isOwnerEdit' => $this->editingId !== null
                && Role::query()->whereKey($this->editingId)->value('name') === 'owner',
        ])->layout('layouts.admin', [
            'title' => __('admin.roles.title'),
            'navigation' => $admin->navigationItems(),
        ]);
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
