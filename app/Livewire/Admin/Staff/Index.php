<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Staff;

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
        $this->authorize('staff.view');
    }

    public function create(): void
    {
        $this->authorize('staff.create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function save(SyncRegisteredPermissions $sync): void
    {
        $this->authorize('staff.create');
        $sync();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('staff_users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', Rule::in(['owner'])],
        ]);

        $staff = StaffUser::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Role::findOrCreate($data['role'], 'staff');
        $staff->syncRoles([$data['role']]);

        session()->flash('status', 'Staff user created.');
        $this->resetForm();
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.staff.index', [
            'staffUsers' => StaffUser::query()->orderBy('name')->paginate(20),
        ])->layout('layouts.admin', [
            'title' => 'Staff',
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
