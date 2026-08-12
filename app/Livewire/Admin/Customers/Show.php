<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Privacy\AnonymizeCustomer;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\StaffUser;
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

    public function mount(Customer $customer): void
    {
        $this->authorize('customers.view');
        $this->customer = $customer;
    }

    public function adjustCredit(CustomerCreditLedger $ledger): void
    {
        $this->authorize('customers.manage');
        $data = $this->validate([
            'entry_type' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        /** @var StaffUser $staff */
        $staff = Auth::guard('staff')->user();

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

    public function render(AdminRegistrar $admin, CustomerCreditLedger $ledger)
    {
        $account = CustomerCreditAccount::query()->where('customer_id', $this->customer->id)->first();

        return view('livewire.admin.customers.show', [
            'balanceAmount' => $ledger->balance($this->customer, $account?->currency),
            'currency' => $account->currency ?? 'EUR',
            'entries' => $this->customer->creditEntries()->latest('id')->limit(50)->get(),
        ])->layout('layouts.admin', [
            'title' => __('admin.customers.customer_title', ['name' => $this->customer->name]),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
