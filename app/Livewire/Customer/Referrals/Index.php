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

        $codes = $customer->referralCodes()
            ->withCount('visits')
            ->withSum('visits', 'clicks_count')
            ->withCount([
                'attributions as paid_purchases_count' => static fn ($query) => $query->where(static function ($query): void {
                    $query->whereNotNull('purchased_at')->orWhere('status', 'posted');
                }),
            ])
            ->withSum([
                'attributions as posted_reward_amount' => static fn ($query) => $query->where('status', 'posted'),
            ], 'reward_amount')
            ->latest('id')
            ->get();
        $codes->each(function ($code) use ($referrals): void {
            $code->setAttribute('effective_window_days', $referrals->windowDaysFor($code));
            $code->setAttribute('referral_link', $referrals->linkFor($code));
        });
        $primaryCode = $codes->first();
        $headlinePercentage = $primaryCode === null
            ? $referrals->defaultRewardPercentage()
            : ($primaryCode->reward_percentage ?? $referrals->defaultRewardPercentage());

        return view($theme->view('account.referrals'), [
            'theme' => $theme,
            'codes' => $codes,
            'attributions' => $customer->referralAttributions()->with('order')->latest('id')->limit(50)->get(),
            'accountBalance' => MoneyFormatter::format($ledger->balance($customer, $currency), $currency),
            'currency' => $currency,
            'defaultRewardPercentage' => $referrals->defaultRewardPercentage(),
            'headlinePercentage' => $headlinePercentage,
            'defaultWindowDays' => $referrals->defaultWindowDays(),
            'headlineWindowDays' => $primaryCode?->effective_window_days ?? $referrals->defaultWindowDays(),
            'referralLink' => $primaryCode !== null ? $referrals->linkFor($primaryCode) : null,
            'linkClicks' => (int) $codes->sum('visits_sum_clicks_count'),
            'uniqueVisitors' => (int) $codes->sum('visits_count'),
            'paidPurchases' => (int) $codes->sum('paid_purchases_count'),
            'earnedRewards' => MoneyFormatter::format((int) $codes->sum('posted_reward_amount'), $currency),
            'referralsEnabled' => $referrals->isEnabled(),
            'accountSection' => 'referrals',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.referrals.title'),
            'theme' => $theme,
        ]);
    }
}
