<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Agovena\Customer\Properties\CustomerPropertyService;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisterCustomer
{
    public function __construct(
        private readonly CustomerRegistration $registration,
        private readonly CustomerPropertyService $properties,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, properties?: array<string, mixed>}  $data
     */
    public function handle(array $data): User
    {
        if (! $this->registration->allowsRegistration()) {
            throw ValidationException::withMessages([
                'email' => __('customer.auth.registration_disabled'),
            ]);
        }

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $customer = $user->ensureCustomer();
            $submitted = is_array($data['properties'] ?? null) ? $data['properties'] : [];
            if ($submitted !== []) {
                $definitions = $this->properties->definitionsFor('registration');
                $this->properties->save($customer, $definitions, $submitted, 'customer');
            }

            $address = $this->properties->addressFromProperties($customer, $submitted);
            if ($address !== null) {
                app(SaveCustomerAddress::class)->handle(
                    $customer,
                    $address,
                    [
                        'label' => __('customer.addresses.checkout_saved_label'),
                        'is_default_billing' => true,
                    ],
                );
            }

            return $user;
        });

        event(new Registered($user));

        return $user;
    }
}
