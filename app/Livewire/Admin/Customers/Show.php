<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Agovena\Privacy\AnonymizeCustomer;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;

    public Customer $customer;

    public string $entry_type = 'credit';

    public int $amount = 0;

    public string $reason = '';

    /** @var array<string, mixed> */
    public array $propertyValues = [];

    public function mount(Customer $customer, CustomerPropertyService $properties): void
    {
        $this->authorize('customers.view');
        $this->customer = $customer;
        $this->propertyValues = $properties->emptyValues($properties->definitionsFor('staff'), $customer);
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
        session()->flash('status', __('admin.customers.anonymized'));
    }

    public function render(AdminRegistrar $admin, CustomerCreditLedger $ledger, CustomerPropertyService $properties)
    {
        $account = CustomerCreditAccount::query()->where('customer_id', $this->customer->id)->first();

        $this->customer->loadMissing('user');

        return view('livewire.admin.customers.show', [
            'balanceAmount' => $ledger->balance($this->customer, $account?->currency),
            'currency' => $account->currency ?? 'EUR',
            'entries' => $this->customer->creditEntries()->latest('id')->limit(50)->get(),
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
