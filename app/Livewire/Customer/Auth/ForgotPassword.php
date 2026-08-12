<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Auth;

use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ForgotPassword extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirect(route('customer.account'), navigate: true);
        }
    }

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker('users')->sendResetLink([
            'email' => $this->email,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        $this->status = __($status);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.auth.forgot-password'), [
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.auth.forgot_title'),
            'theme' => $theme,
        ]);
    }
}
