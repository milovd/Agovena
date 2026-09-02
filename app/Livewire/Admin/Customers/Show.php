<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\AdminRoleAssignmentPolicy;
use App\Agovena\Auth\ManageUserSessions;
use App\Agovena\Auth\SetUserEmailVerification;
use App\Agovena\Auth\TotpTwoFactor;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Agovena\Customer\SetCustomerPassword;
use App\Agovena\Customer\UpdateCustomerProfile;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Privacy\AnonymizeCustomer;
use App\Agovena\Privacy\DeleteCustomerAccount;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;

    public Customer $customer;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $entry_type = 'credit';

    public int $amount = 0;

    public string $reason = '';

    /** @var array<string, mixed> */
    public array $propertyValues = [];

    /** @var list<string> */
    public array $selectedRoles = [];

    public function mount(Customer $customer, CustomerPropertyService $properties): void
    {
        $this->authorize('customers.view');
        $this->customer = $customer;
        $this->customer->loadMissing('user.roles');
        $this->name = (string) $customer->name;
        $this->email = (string) $customer->email;
        $this->selectedRoles = $this->customer->user?->roles->pluck('name')->values()->all() ?? [];
        $this->propertyValues = $properties->emptyValues($properties->definitionsFor('staff'), $customer);
    }

    public function saveProfile(UpdateCustomerProfile $update): void
    {
        $this->authorize('customers.manage');

        if ($this->customer->anonymized_at !== null) {
            return;
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->customer = $update->handle($this->customer, $data);
        $this->customer->loadMissing('user.roles');
        $this->name = (string) $this->customer->name;
        $this->email = (string) $this->customer->email;
        session()->flash('status', __('admin.customers.profile_saved'));
    }

    public function saveRoles(AdminRoleAssignmentPolicy $rolePolicy, SyncRegisteredPermissions $sync): void
    {
        $this->authorize('users.update');

        if (! $this->requireRecentPassword('saveRoles')) {
            return;
        }

        $sync();
        $actor = $this->actor();
        $this->customer->loadMissing('user');
        if (! $this->customer->user instanceof User) {
            return;
        }
        $target = $this->customer->user;
        $roleNames = $rolePolicy->grantableRoles($actor, $target)->pluck('name')->all();

        $data = $this->validate([
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string', 'distinct', Rule::in($roleNames)],
        ]);

        $target = $rolePolicy->syncRoles($actor, $target, $data['selectedRoles'] ?? [], 'selectedRoles');
        $target->load('roles');
        $this->selectedRoles = $target->roles->pluck('name')->values()->all();
        session()->flash('status', __('admin.customers.roles_saved'));
    }

    public function saveProperties(CustomerPropertyService $properties): void
    {
        $this->authorize('customers.manage');

        if ($this->customer->anonymized_at !== null) {
            return;
        }

        $definitions = $properties->definitionsFor('staff');
        $data = $this->validate($properties->livewireRules($definitions));
        $submitted = $data['propertyValues'] ?? $this->propertyValues;
        $properties->save($this->customer, $definitions, $submitted, 'staff');

        $address = $properties->addressFromProperties($this->customer, $submitted);
        if ($address !== null) {
            $existing = $this->customer->addresses()->where('is_default_billing', true)->first();
            app(SaveCustomerAddress::class)->handle(
                $this->customer,
                $address,
                [
                    'label' => optional($existing)->label ?? __('customer.addresses.checkout_saved_label'),
                    'is_default_billing' => true,
                    'is_default_shipping' => (bool) (optional($existing)->is_default_shipping ?? false),
                ],
                $existing,
            );
        }

        $this->customer->load('addresses');
        session()->flash('status', __('admin.customer_properties.values_saved'));
    }

    public function changePassword(SetCustomerPassword $passwords, ManageUserSessions $sessions): void
    {
        $this->authorize('customers.manage');

        if ($this->customer->anonymized_at !== null) {
            return;
        }

        $this->customer->loadMissing('user');
        $user = $this->customer->user;
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'password' => __('admin.customers.password_no_user'),
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'password' => __('admin.customers.password_disable_two_factor_first'),
            ]);
        }

        if (! $this->requireRecentPassword('changePassword')) {
            return;
        }

        $data = $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $passwords->handle($this->customer, $data['password']);
        $sessions->revokeOthers($user);
        $this->reset(['password', 'password_confirmation']);
        session()->flash('status', __('admin.customers.password_changed'));
    }

    public function disableTwoFactor(TotpTwoFactor $totp): void
    {
        $this->authorize('customers.manage');

        if (! $this->requireRecentPassword('disableTwoFactor')) {
            return;
        }

        $this->customer->loadMissing('user');
        $user = $this->customer->user;
        if (! $user instanceof User || ! $user->hasTwoFactorEnabled()) {
            return;
        }

        $totp->disable($user);
        $this->customer->load('user.roles');
        session()->flash('status', __('admin.customers.two_factor_disabled'));
    }

    public function adjustCredit(CustomerCreditLedger $ledger): void
    {
        $this->authorize('customers.manage');

        if (! $this->requireRecentPassword('adjustCredit')) {
            return;
        }

        $data = $this->validate([
            'entry_type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        /** @var User $staff */
        $staff = Auth::user();

        $ledger->{$data['entry_type']}($this->customer, $data['amount'], $data['reason'], staff: $staff);
        $this->reset(['amount', 'reason']);
        session()->flash('status', __('admin.customers.credit_adjusted'));
    }

    public function anonymize(AnonymizeCustomer $anonymize): void
    {
        $this->authorize('customers.manage');

        if (! $this->requireRecentPassword('anonymize')) {
            return;
        }

        $this->customer = $anonymize->handle($this->customer);
        $this->customer->loadMissing('user.roles', 'addresses');
        $this->name = (string) $this->customer->name;
        $this->email = (string) $this->customer->email;
        $this->propertyValues = [];
        session()->flash('status', __('admin.customers.anonymized'));
    }

    public function fullDelete(DeleteCustomerAccount $deletion): void
    {
        $this->authorize('customers.manage');

        if (! $this->requireRecentPassword('fullDelete')) {
            return;
        }

        $deletion->handle($this->customer);
        session()->flash('status', __('admin.customers.deleted_completely'));
        $this->redirect(route('admin.customers.index'), navigate: true);
    }

    public function markEmailVerified(SetUserEmailVerification $verification): void
    {
        $this->setEmailVerification($verification, true);
    }

    public function markEmailUnverified(SetUserEmailVerification $verification): void
    {
        $this->setEmailVerification($verification, false);
    }

    public function render(
        AdminRegistrar $admin,
        AdminRoleAssignmentPolicy $rolePolicy,
        CustomerCreditLedger $ledger,
        CustomerPropertyService $properties,
        DeleteCustomerAccount $deletion,
    ) {
        $this->authorize('customers.view');

        $this->customer->loadMissing(['user.roles', 'addresses']);
        $account = CustomerCreditAccount::query()->where('customer_id', $this->customer->id)->first();
        $user = $this->customer->user;

        $recentOrders = $this->customer->orders()->latest('id')->limit(6)->get();
        $recentInvoices = $this->customer->invoices()->latest('id')->limit(6)->get();
        $recentCreditNotes = $this->customer->creditNotes()->latest('id')->limit(6)->get();
        $recentTickets = $this->customer->tickets()->latest('id')->limit(6)->get();
        $recentRefunds = Refund::query()
            ->whereHas('order', fn ($query) => $query->where('customer_id', $this->customer->id))
            ->latest('id')
            ->limit(6)
            ->get();

        return view('livewire.admin.customers.show', [
            'balanceAmount' => $ledger->balance($this->customer, $account?->currency),
            'availableAmount' => $ledger->available($this->customer, $account?->currency),
            'reservedAmount' => $ledger->reserved($this->customer, $account?->currency),
            'currency' => $account === null ? 'EUR' : $account->currency,
            'entries' => $this->customer->creditEntries()->latest('id')->limit(8)->get(),
            'recentOrders' => $recentOrders,
            'recentInvoices' => $recentInvoices,
            'recentCreditNotes' => $recentCreditNotes,
            'recentTickets' => $recentTickets,
            'recentRefunds' => $recentRefunds,
            'addresses' => $this->customer->addresses,
            'stats' => [
                'orders' => $this->customer->orders()->count(),
                'invoices' => $this->customer->invoices()->count(),
                'tickets' => $this->customer->tickets()->count(),
                'creditNotes' => $this->customer->creditNotes()->count(),
            ],
            'user' => $user,
            'availableRoles' => $user instanceof User
                ? $rolePolicy->grantableRoles($this->actor(), $user)
                : collect(),
            'customerDetailSections' => $admin->customerDetailSections(),
            'propertyDefinitions' => $properties->definitionsFor('staff'),
            'fullDeleteBlockers' => $deletion->blockingReasons($this->customer),
            'actor' => 'staff',
            'propertyEditable' => auth()->user()?->can('customers.manage') ?? false,
            'fieldClass' => 'ag-field',
            'labelClass' => 'ag-field__label',
            'inputClass' => 'ag-input',
            'errorClass' => 'ag-field__error',
            'checkClass' => 'ag-check',
        ])->layout('layouts.admin', [
            'title' => __('admin.customers.customer_title', ['name' => $this->customer->name]),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function setEmailVerification(SetUserEmailVerification $verification, bool $verified): void
    {
        $this->authorize('customers.manage');

        if (! $this->requireRecentPassword($verified ? 'markEmailVerified' : 'markEmailUnverified')) {
            return;
        }

        $this->customer->loadMissing('user');
        if (! $this->customer->user instanceof User) {
            return;
        }

        $verification->handle($this->customer->user, $verified);
        $this->customer->user->refresh();

        session()->flash('status', __(
            $verified
                ? 'admin.customers.email_marked_verified'
                : 'admin.customers.email_marked_unverified',
        ));
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
