<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Customer\ChangeCustomerPassword;
use App\Agovena\Customer\UpdateCustomerProfile;
use App\Agovena\Privacy\ExportCustomerData;
use App\Agovena\Privacy\RequestAccountDeletion;
use App\Agovena\Theme\ThemeManager;
use App\Models\Customer;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $this->name = $customer->name;
        $this->email = $customer->email;
    }

    public function saveProfile(UpdateCustomerProfile $update): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        /** @var Customer $customer */
        $customer = authenticated_customer();
        $update->handle($customer, $data);

        session()->flash('status', __('customer.profile.saved'));

        if (! $customer->fresh()?->hasVerifiedEmail()) {
            $this->redirect(route('customer.verification.notice'), navigate: true);
        }
    }

    public function changePassword(ChangeCustomerPassword $changePassword): void
    {
        $data = $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        /** @var Customer $customer */
        $customer = authenticated_customer();
        $changePassword->handle($customer, $data['current_password'], $data['password']);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('status', __('customer.profile.password_changed'));
    }

    public function exportData(ExportCustomerData $export): StreamedResponse
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $json = json_encode($export->handle($customer), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return response()->streamDownload(
            static function () use ($json): void {
                echo $json;
            },
            'agovena-customer-data.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function requestDeletion(RequestAccountDeletion $requestDeletion): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $requestDeletion->handle($customer);
        session()->flash('status', __('customer.privacy.deletion_requested'));
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $customer = authenticated_customer();

        return view($theme->view('account.profile'), [
            'theme' => $theme,
            'emailVerified' => $customer->hasVerifiedEmail(),
            'deletionRequested' => $customer->deletion_requested_at !== null,
            'accountSection' => 'profile',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.profile_title'),
            'theme' => $theme,
        ]);
    }
}
