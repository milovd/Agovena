<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Users;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\StaffUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $showForm = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'owner';

    public function mount(): void
    {
        $this->authorize('users.view');
    }

    public function create(): void
    {
        $this->authorize('users.create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(SyncRegisteredPermissions $sync): void
    {
        $this->authorize('users.create');
        $sync();

        $roleNames = Role::query()
            ->where('guard_name', 'staff')
            ->pluck('name')
            ->all();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('staff_users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::in($roleNames)],
        ]);

        $user = StaffUser::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles([$data['role']]);

        session()->flash('status', __('admin.users.created'));
        $this->resetForm();
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.users.index', [
            'users' => StaffUser::query()->with('roles')->orderBy('name')->paginate(20),
            'roles' => Role::query()->where('guard_name', 'staff')->orderBy('name')->get(),
        ])->layout('layouts.admin', [
            'title' => __('admin.users.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = 'owner';
        $this->resetValidation();
    }
}
