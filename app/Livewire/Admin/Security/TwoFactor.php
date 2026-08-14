<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Security;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Auth\TotpTwoFactor;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class TwoFactor extends Component
{
    use RequiresRecentPassword;

    public string $code = '';

    /** @var list<string> */
    public array $recoveryCodes = [];

    public bool $showingRecoveryCodes = false;

    public function startSetup(TotpTwoFactor $totp): void
    {
        $user = $this->staff();
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
        $user = $this->staff();
        if ($user->hasTwoFactorEnabled()) {
            return;
        }

        $secret = (string) session(TotpTwoFactor::SESSION_SETUP_SECRET, '');
        if ($secret === '') {
            throw ValidationException::withMessages([
                'code' => __('admin.security.setup_expired'),
            ]);
        }

        $this->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $totp->verify($secret, $this->code)) {
            throw ValidationException::withMessages([
                'code' => __('admin.security.invalid_code'),
            ]);
        }

        $plain = $totp->generateRecoveryCodes();
        $totp->enable($user, $secret, $totp->hashRecoveryCodes($plain));
        session()->forget(TotpTwoFactor::SESSION_SETUP_SECRET);

        $this->recoveryCodes = $plain;
        $this->showingRecoveryCodes = true;
        $this->code = '';
        session()->flash('status', __('admin.security.enabled'));
    }

    public function hideRecoveryCodes(): void
    {
        $this->recoveryCodes = [];
        $this->showingRecoveryCodes = false;
    }

    public function disable(TotpTwoFactor $totp): void
    {
        $user = $this->staff();
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
        session()->flash('status', __('admin.security.disabled'));
    }

    public function render(AdminRegistrar $admin, TotpTwoFactor $totp)
    {
        $user = $this->staff();
        $setupSecret = (string) session(TotpTwoFactor::SESSION_SETUP_SECRET, '');

        return view('livewire.admin.security.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'forced' => (bool) config('agovena.security.privileged_two_factor', true) && ! $user->hasTwoFactorEnabled(),
            'setupSecret' => $setupSecret,
            'qrSvg' => $setupSecret !== '' ? $totp->qrSvg($user->email, $setupSecret) : null,
        ])->layout('layouts.admin', [
            'title' => __('admin.security.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function staff(): User
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->canAccessAdmin()) {
            abort(403);
        }

        return $user;
    }
}
