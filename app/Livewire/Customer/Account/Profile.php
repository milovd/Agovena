<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Customer\ChangeCustomerPassword;
use App\Agovena\Customer\Properties\CustomerPropertyService;
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

    /** @var array<string, mixed> */
    public array $propertyValues = [];

    public function mount(CustomerPropertyService $properties): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $this->name = $customer->name;
        $this->email = $customer->email;
        $this->propertyValues = $properties->emptyValues($properties->definitionsFor('account'), $customer);
    }

    public function saveProfile(UpdateCustomerProfile $update, CustomerPropertyService $properties): void
    {
        $definitions = $properties->nonAddressDefinitionsFor('account');
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            ...$properties->livewireRules($definitions),
        ]);

        /** @var Customer $customer */
        $customer = authenticated_customer();
        $update->handle($customer, $data);
        $properties->save($customer, $definitions, $data['propertyValues'] ?? $this->propertyValues, 'customer');

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

    public function render(ThemeManager $themes, CustomerPropertyService $properties)
    {
        $theme = $themes->active();
        $customer = authenticated_customer();

        return view($theme->view('account.profile'), [
            'theme' => $theme,
            'emailVerified' => $customer->hasVerifiedEmail(),
            'deletionRequested' => $customer->deletion_requested_at !== null,
            'accountSection' => 'profile',
            'propertyDefinitions' => $properties->nonAddressDefinitionsFor('account'),
            'actor' => 'customer',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.profile_title'),
            'theme' => $theme,
        ]);
    }
}
