<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Customer\UpdateCustomerProfile;
use App\Agovena\Privacy\AnonymizeCustomer;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;

    public Customer $customer;

    public string $panel = 'overview';

    public string $name = '';

    public string $email = '';

    public string $entry_type = 'credit';

    public int $amount = 0;

    public string $reason = '';

    /** @var array<string, mixed> */
    public array $propertyValues = [];

    public function mount(Customer $customer, CustomerPropertyService $properties): void
    {
        $this->authorize('customers.view');
        $this->customer = $customer;
        $this->customer->loadMissing('user');
        $this->name = (string) $customer->name;
        $this->email = (string) $customer->email;
        $this->propertyValues = $properties->emptyValues($properties->definitionsFor('staff'), $customer);
    }

    public function selectPanel(string $panel): void
    {
        $allowed = ['overview', 'profile', 'addresses', 'commerce', 'credits', 'capabilities'];
        if (! in_array($panel, $allowed, true)) {
            return;
        }

        $this->panel = $panel;
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
        $this->customer->loadMissing('user');
        $this->name = (string) $this->customer->name;
        $this->email = (string) $this->customer->email;
        session()->flash('status', __('admin.customers.profile_saved'));
    }

    public function saveProperties(CustomerPropertyService $properties): void
    {
        $this->authorize('customers.manage');
        $definitions = $properties->definitionsFor('staff');
        $data = $this->validate($properties->livewireRules($definitions));
        $properties->save($this->customer, $definitions, $data['propertyValues'] ?? $this->propertyValues, 'staff');
        session()->flash('status', __('admin.customer_properties.values_saved'));
    }

    public function adjustCredit(CustomerCreditLedger $ledger): void
    {
        $this->authorize('customers.manage');
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
        $this->customer = $anonymize->handle($this->customer);
        $this->customer->loadMissing('user');
        $this->name = (string) $this->customer->name;
        $this->email = (string) $this->customer->email;
        session()->flash('status', __('admin.customers.anonymized'));
    }

    public function render(AdminRegistrar $admin, CustomerCreditLedger $ledger, CustomerPropertyService $properties)
    {
        $account = CustomerCreditAccount::query()->where('customer_id', $this->customer->id)->first();

        $this->customer->loadMissing(['user.roles', 'addresses']);

        $user = $this->customer->user;
        $recentOrders = $this->customer->orders()->latest('id')->limit(8)->get();
        $recentInvoices = $this->customer->invoices()->latest('id')->limit(8)->get();
        $recentCreditNotes = $this->customer->creditNotes()->latest('id')->limit(8)->get();
        $recentTickets = $this->customer->tickets()->latest('id')->limit(8)->get();
        $recentRefunds = Refund::query()
            ->whereHas('order', fn ($query) => $query->where('customer_id', $this->customer->id))
            ->latest('id')
            ->limit(8)
            ->get();

        return view('livewire.admin.customers.show', [
            'balanceAmount' => $ledger->balance($this->customer, $account?->currency),
            'currency' => $account === null ? 'EUR' : $account->currency,
            'entries' => $this->customer->creditEntries()->latest('id')->limit(50)->get(),
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
                'addresses' => $this->customer->addresses->count(),
            ],
            'user' => $user,
            'customerDetailSections' => $admin->customerDetailSections(),
            'propertyDefinitions' => $properties->definitionsFor('staff'),
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
}
