<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Auth\ManageUserSessions;
use App\Agovena\Auth\TotpTwoFactor;
use App\Agovena\Theme\ThemeManager;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Security extends Component
{
    use RequiresRecentPassword;

    public string $code = '';

    /** @var list<string> */
    public array $recoveryCodes = [];

    public bool $showingRecoveryCodes = false;

    public function startSetup(TotpTwoFactor $totp): void
    {
        $user = $this->accountUser();
        if ($user->hasTwoFactorEnabled()) {
            return;
        }

        $secret = $totp->generateSecret();
        session([TotpTwoFactor::SESSION_SETUP_SECRET => $secret]);
        $this->code = '';
        $this->recoveryCodes = [];
        $this->showingRecoveryCodes = false;
        $this->resetValidation();
    }

    public function confirmSetup(TotpTwoFactor $totp): void
    {
        $user = $this->accountUser();
        if ($user->hasTwoFactorEnabled()) {
            return;
        }

        $secret = (string) session(TotpTwoFactor::SESSION_SETUP_SECRET, '');
        if ($secret === '') {
            throw ValidationException::withMessages([
                'code' => __('customer.security.setup_expired'),
            ]);
        }

        $this->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $totp->verify($secret, $this->code)) {
            throw ValidationException::withMessages([
                'code' => __('customer.security.invalid_code'),
            ]);
        }

        $plain = $totp->generateRecoveryCodes();
        $totp->enable($user, $secret, $totp->hashRecoveryCodes($plain));
        session()->forget(TotpTwoFactor::SESSION_SETUP_SECRET);
        $totp->markVerified($user);

        $this->recoveryCodes = $plain;
        $this->showingRecoveryCodes = true;
        $this->code = '';
        session()->flash('status', __('customer.security.enabled'));
    }

    public function regenerateRecoveryCodes(TotpTwoFactor $totp): void
    {
        $user = $this->accountUser();
        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        if (! $this->requireRecentPassword('regenerateRecoveryCodes')) {
            return;
        }

        $plain = $totp->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $totp->hashRecoveryCodes($plain),
        ])->save();

        $this->recoveryCodes = $plain;
        $this->showingRecoveryCodes = true;
        session()->flash('status', __('customer.security.recovery_regenerated'));
    }

    public function hideRecoveryCodes(): void
    {
        $this->recoveryCodes = [];
        $this->showingRecoveryCodes = false;
    }

    public function disable(TotpTwoFactor $totp): void
    {
        $user = $this->accountUser();
        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        if (! $this->requireRecentPassword('disable')) {
            return;
        }

        $totp->disable($user);
        session()->forget(TotpTwoFactor::SESSION_SETUP_SECRET);
        $this->recoveryCodes = [];
        $this->showingRecoveryCodes = false;
        session()->flash('status', __('customer.security.disabled'));
    }

    public function revokeSession(string $sessionId, ManageUserSessions $sessions): void
    {
        $user = $this->accountUser();
        if ($sessions->revoke($user, $sessionId)) {
            session()->flash('status', __('customer.security.session_revoked'));
        }
    }

    public function revokeOtherSessions(ManageUserSessions $sessions): void
    {
        $user = $this->accountUser();
        if (! $this->requireRecentPassword('revokeOtherSessions')) {
            return;
        }

        $count = $sessions->revokeOthers($user);
        session()->flash('status', __('customer.security.sessions_revoked', ['count' => $count]));
    }

    public function render(ThemeManager $themes, TotpTwoFactor $totp, ManageUserSessions $sessions)
    {
        $theme = $themes->active();
        $user = $this->accountUser();
        $setupSecret = (string) session(TotpTwoFactor::SESSION_SETUP_SECRET, '');
        $forced = (bool) config('agovena.security.privileged_two_factor', true)
            && $user->canAccessAdmin()
            && ! $user->hasTwoFactorEnabled();

        return view($theme->view('account.security'), [
            'theme' => $theme,
            'accountSection' => 'security',
            'enabled' => $user->hasTwoFactorEnabled(),
            'forced' => $forced,
            'setupSecret' => $setupSecret,
            'qrSvg' => $setupSecret !== '' ? $totp->qrSvg($user->email, $setupSecret) : null,
            'sessions' => $sessions->listFor($user),
            'sessionsSupported' => $sessions->usesDatabaseDriver(),
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.security.title'),
            'theme' => $theme,
        ]);
    }

    private function accountUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
