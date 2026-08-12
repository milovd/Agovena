<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Auth;

use App\Agovena\Customer\CustomerRegistration;
use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(CustomerRegistration $registration): void
    {
        if (Auth::guard('customer')->check()) {
            $this->redirect(route('customer.account'), navigate: true);
        }
    }

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $key = 'customer-login:'.mb_strtolower($credentials['email']).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => __('customer.auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        if (! Auth::guard('customer')->attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $credentials['remember'],
        )) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => __('customer.auth.failed'),
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        $this->redirectIntended(route('customer.account'), navigate: true);
    }

    public function render(ThemeManager $themes, CustomerRegistration $registration)
    {
        $theme = $themes->active();

        return view($theme->view('account.auth.login'), [
            'theme' => $theme,
            'registrationEnabled' => $registration->allowsRegistration(),
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.auth.login_title'),
            'theme' => $theme,
        ]);
    }
}
