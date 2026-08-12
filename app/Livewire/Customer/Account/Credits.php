<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Money\Money;
use App\Agovena\Theme\ThemeManager;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

final class Credits extends Component
{
    use WithPagination;

    public function render(ThemeManager $themes, CustomerCreditLedger $ledger)
    {
        $theme = $themes->active();
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        $account = CustomerCreditAccount::query()->where('customer_id', $customer->id)->first();
        $currency = $account->currency ?? 'EUR';

        return view($theme->view('account.credits'), [
            'balance' => Money::of($ledger->balance($customer, $currency), $currency),
            'entries' => $customer->creditEntries()->latest('id')->paginate(20),
            'accountSection' => 'credits',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.credits.title'),
            'theme' => $theme,
        ]);
    }
}
