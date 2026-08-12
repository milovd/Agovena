<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Logout extends Component
{
    public function mount(): void
    {
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
