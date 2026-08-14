<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Agovena\Auth\TotpTwoFactor;
use App\Agovena\Theme\ThemeManager;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class TwoFactorChallenge extends Component
{
    public string $code = '';

    public bool $recovery = false;

    public string $recovery_code = '';

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirect(route('customer.account'), navigate: true);

            return;
        }

        if (! session()->has(TotpTwoFactor::SESSION_PENDING_ID)) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function authenticate(TotpTwoFactor $totp): void
    {
        $user = $this->pendingUser();
        $key = 'two-factor:'.$user->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                $this->recovery ? 'recovery_code' : 'code' => __('customer.auth.two_factor.throttle', ['seconds' => $seconds]),
            ]);
        }

        $ok = $this->recovery
            ? $this->consumeRecovery($totp, $user)
            : $totp->verify((string) $user->two_factor_secret, $this->code);

        if (! $ok) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                $this->recovery ? 'recovery_code' : 'code' => __('customer.auth.two_factor.invalid'),
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) session()->pull(TotpTwoFactor::SESSION_PENDING_REMEMBER, false);
        $intended = (string) session()->pull(TotpTwoFactor::SESSION_PENDING_INTENDED, '');
        session()->forget(TotpTwoFactor::SESSION_PENDING_ID);

        Auth::login($user, $remember);
        session()->regenerate();
        $totp->markVerified($user);

        $this->redirect($this->destination($user, $intended), navigate: true);
    }

    public function useRecovery(): void
    {
        $this->recovery = true;
        $this->code = '';
        $this->resetValidation();
    }

    public function useCode(): void
    {
        $this->recovery = false;
        $this->recovery_code = '';
        $this->resetValidation();
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.auth.two-factor-challenge'), [
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.auth.two_factor.title'),
            'theme' => $theme,
        ]);
    }

    private function pendingUser(): User
    {
        $id = session(TotpTwoFactor::SESSION_PENDING_ID);
        $user = is_numeric($id) ? User::query()->find((int) $id) : null;
        if (! $user instanceof User || ! $user->hasTwoFactorEnabled()) {
            session()->forget([
                TotpTwoFactor::SESSION_PENDING_ID,
                TotpTwoFactor::SESSION_PENDING_REMEMBER,
                TotpTwoFactor::SESSION_PENDING_INTENDED,
            ]);

            $this->redirect(route('login'), navigate: true);
            throw ValidationException::withMessages([
                'code' => __('customer.auth.two_factor.expired'),
            ]);
        }

        return $user;
    }

    private function consumeRecovery(TotpTwoFactor $totp, User $user): bool
    {
        $remaining = $totp->consumeRecoveryCode(
            is_array($user->two_factor_recovery_codes) ? $user->two_factor_recovery_codes : null,
            $this->recovery_code,
        );
        if ($remaining === null) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();

        return true;
    }

    private function destination(User $user, string $intended): string
    {
        $default = $user->canAccessAdmin() ? route('admin.dashboard') : route('customer.account');
        if ($intended === '') {
            return $default;
        }

        $path = parse_url($intended, PHP_URL_PATH) ?? $intended;
        $isAdmin = is_string($path) && (str_starts_with($path, '/admin') || str_contains($path, '/admin/'));
        if ($isAdmin && ! $user->canAccessAdmin()) {
            return route('customer.account');
        }

        return $intended;
    }
}
