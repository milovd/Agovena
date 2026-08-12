<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Auth;

use App\Agovena\Customer\CustomerRegistration;
use App\Agovena\Customer\RegisterCustomer;
use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

final class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(CustomerRegistration $registration): void
    {
        if (Auth::check()) {
            $this->redirect(route('customer.account'), navigate: true);

            return;
        }

        if (! $registration->allowsRegistration()) {
            $this->redirect(route('login'), navigate: true);
        }
    }

    public function register(RegisterCustomer $registerCustomer): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = $registerCustomer->handle([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Auth::login($user);
        session()->regenerate();

        $this->redirect(route('customer.verification.notice'), navigate: true);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.auth.register'), [
            'theme' => $theme,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.auth.register_title'),
            'theme' => $theme,
        ]);
    }
}
