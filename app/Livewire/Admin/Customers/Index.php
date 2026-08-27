<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\AdminRoleAssignmentPolicy;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\OrderStatus;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

final class Index extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public bool $showUserForm = false;

    public string $userName = '';

    public string $userEmail = '';

    public string $userPassword = '';

    public string $userRole = 'owner';

    public function mount(): void
    {
        $this->authorize('customers.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function createUser(AdminRoleAssignmentPolicy $rolePolicy): void
    {
        $this->authorize('users.create');
        $this->resetUserForm();
        $firstRole = $rolePolicy->grantableRoles($this->actor())->first();
        $this->userRole = $firstRole instanceof Role ? $firstRole->name : '';
        $this->showUserForm = true;
    }

    public function saveUser(AdminRoleAssignmentPolicy $rolePolicy, SyncRegisteredPermissions $sync): void
    {
        $this->authorize('users.create');

        if (! $this->requireRecentPassword('saveUser')) {
            return;
        }

        $sync();
        $roleNames = $rolePolicy->grantableRoles($this->actor())->pluck('name')->all();

        $data = $this->validate([
            'userName' => ['required', 'string', 'max:255'],
            'userEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'userPassword' => ['required', 'string', Password::defaults()],
            'userRole' => ['required', 'string', Rule::in($roleNames)],
        ]);

        $role = Role::query()
            ->where('guard_name', User::GUARD)
            ->where('name', $data['userRole'])
            ->with('permissions')
            ->firstOrFail();
        $rolePolicy->assertCanGrantRole($this->actor(), $role, 'userRole');

        $user = User::query()->create([
            'name' => $data['userName'],
            'email' => $data['userEmail'],
            'password' => $data['userPassword'],
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$data['userRole']]);

        session()->flash('status', __('admin.customers.user_created'));
        $this->resetUserForm();
        $this->resetPage();
    }

    public function cancelUser(): void
    {
        $this->resetUserForm();
    }

    public function render(AdminRegistrar $admin, AdminRoleAssignmentPolicy $rolePolicy)
    {
        return view('livewire.admin.customers.index', [
            'customers' => Customer::query()
                ->with(['user.roles', 'creditAccount'])
                ->withCount('orders')
                ->withSum([
                    'orders as paid_orders_total' => fn ($query) => $query->where('status', OrderStatus::Paid),
                ], 'total_amount')
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(function ($nested) use ($term): void {
                        $nested->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
                })
                ->when($this->status === 'anonymized', fn ($query) => $query->whereNotNull('anonymized_at'))
                ->when($this->status === 'deletion', fn ($query) => $query->whereNotNull('deletion_requested_at')->whereNull('anonymized_at'))
                ->when($this->status === 'active', fn ($query) => $query->whereNull('anonymized_at')->whereNull('deletion_requested_at'))
                ->latest('id')
                ->paginate(20),
            'roles' => $rolePolicy->grantableRoles($this->actor()),
        ])->layout('layouts.admin', [
            'title' => __('admin.customers.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetUserForm(): void
    {
        $this->showUserForm = false;
        $this->userName = '';
        $this->userEmail = '';
        $this->userPassword = '';
        $this->userRole = 'owner';
        $this->resetValidation();
    }

    private function actor(): User
    {
        $actor = Auth::user();
        if (! $actor instanceof User) {
            abort(403);
        }

        return $actor;
    }
}
