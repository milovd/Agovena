<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Auth;

use App\Agovena\Auth\ConfirmsRecentPassword;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Logout extends Component
{
    public function mount(): void
    {
        app(ConfirmsRecentPassword::class)->forget();
        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('storefront.home'), navigate: true);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
