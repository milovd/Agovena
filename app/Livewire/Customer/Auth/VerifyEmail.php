<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Auth;

use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class VerifyEmail extends Component
{
    public function mount(): void
    {
        $customer = Auth::guard('customer')->user();

        if ($customer !== null && $customer->hasVerifiedEmail()) {
            $this->redirect(route('customer.account'), navigate: true);
        }
    }

    public function resend(): void
    {
        $customer = Auth::guard('customer')->user();

        if ($customer === null) {
            $this->redirect(route('customer.login'), navigate: true);

            return;
        }

        if ($customer->hasVerifiedEmail()) {
            $this->redirect(route('customer.account'), navigate: true);

            return;
        }

        $customer->sendEmailVerificationNotification();

        session()->flash('status', __('customer.auth.verification_sent'));
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.auth.verify-email'), [
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.auth.verify_title'),
            'theme' => $theme,
        ]);
    }
}
