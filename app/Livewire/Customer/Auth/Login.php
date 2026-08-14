<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Auth;

use App\Agovena\Auth\TotpTwoFactor;
use App\Agovena\Customer\CustomerRegistration;
use App\Agovena\Theme\ThemeManager;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (session()->has(TotpTwoFactor::SESSION_PENDING_ID)) {
            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        if (Auth::check()) {
            $this->redirect($this->destination(Auth::user()), navigate: true);
        }
    }

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $key = 'login:'.mb_strtolower($credentials['email']).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => __('customer.auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        if (! Auth::attempt(
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

        $user = Auth::user();
        session([
            TotpTwoFactor::SESSION_PRIVILEGED_AT_LOGIN => $user instanceof User && $user->canAccessAdmin(),
        ]);

        if ($user instanceof User && $user->hasTwoFactorEnabled()) {
            $intended = (string) (session()->pull('url.intended') ?: '');
            Auth::logout();
            session([
                TotpTwoFactor::SESSION_PENDING_ID => $user->id,
                TotpTwoFactor::SESSION_PENDING_REMEMBER => $this->remember,
                TotpTwoFactor::SESSION_PENDING_INTENDED => $intended,
            ]);
            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        $this->redirect($this->destination($user), navigate: true);
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

    private function destination(mixed $user): string
    {
        $default = route('customer.account');
        $intended = (string) (session()->pull('url.intended') ?: $default);

        if (! $user instanceof User) {
            return $default;
        }

        if ($this->isAdminUrl($intended)) {
            return $user->canAccessAdmin() ? $intended : $default;
        }

        return $intended !== '' ? $intended : $default;
    }

    private function isAdminUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        return is_string($path) && (str_starts_with($path, '/admin') || str_contains($path, '/admin/'));
    }
}
