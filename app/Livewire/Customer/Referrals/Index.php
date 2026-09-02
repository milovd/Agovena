<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Referrals;

use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Referrals\ReferralService;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use App\Support\MoneyFormatter;
use Livewire\Component;

final class Index extends Component
{
    public string $newCode = '';

    public function mount(): void
    {
        $customer = authenticated_customer();
        $this->newCode = 'REF-'.$customer->id;
    }

    public function createCode(ReferralService $referrals): void
    {
        if (! $referrals->isEnabled()) {
            $this->addError('newCode', __('customer.referrals.disabled'));

            return;
        }

        $this->validate([
            'newCode' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{2,63}$/'],
        ]);

        $referrals->createCode(authenticated_customer(), $this->newCode);
        session()->flash('status', __('customer.referrals.created'));
    }

    public function render(
        ThemeManager $themes,
        ReferralService $referrals,
        CustomerCreditLedger $ledger,
        SettingsRepository $settings,
    ) {
        $theme = $themes->active();
        $customer = authenticated_customer();
        $currency = strtoupper((string) $settings->get('general', 'base_currency', 'EUR'));

        return view($theme->view('account.referrals'), [
            'theme' => $theme,
            'codes' => $customer->referralCodes()->latest('id')->get(),
            'attributions' => $customer->referralAttributions()->with('order')->latest('id')->limit(50)->get(),
            'accountBalance' => MoneyFormatter::format($ledger->balance($customer, $currency), $currency),
            'currency' => $currency,
            'defaultRewardPercentage' => $referrals->defaultRewardPercentage(),
            'referralsEnabled' => $referrals->isEnabled(),
            'accountSection' => 'referrals',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.referrals.title'),
            'theme' => $theme,
        ]);
    }
}
