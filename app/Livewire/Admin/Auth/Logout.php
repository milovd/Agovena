<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Logout extends Component
{
    public function logout(): void
    {
        Auth::guard('staff')->logout();

        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('admin.login'), navigate: false);
    }

    public function render()
    {
        return view('livewire.admin.auth.logout');
    }
}
