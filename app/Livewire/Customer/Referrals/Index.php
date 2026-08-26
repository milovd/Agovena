<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Referrals;

use App\Agovena\Referrals\ReferralService;
use App\Agovena\Theme\ThemeManager;
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
        $this->validate([
            'newCode' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{2,63}$/'],
        ]);

        $referrals->createCode(authenticated_customer(), $this->newCode);
        session()->flash('status', __('customer.referrals.created'));
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $customer = authenticated_customer();

        return view($theme->view('account.referrals'), [
            'theme' => $theme,
            'codes' => $customer->referralCodes()->latest('id')->get(),
            'attributions' => $customer->referralAttributions()->with('order')->latest('id')->limit(50)->get(),
            'accountSection' => 'referrals',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.referrals.title'),
            'theme' => $theme,
        ]);
    }
}
