<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Auth;

use App\Agovena\Theme\ThemeManager;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        if (Auth::check()) {
            $this->redirect(route('customer.account'), navigate: true);

            return;
        }

        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('users')->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        session()->flash('status', __($status));

        $this->redirect(route('login'), navigate: true);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.auth.reset-password'), [
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.auth.reset_title'),
            'theme' => $theme,
        ]);
    }
}
