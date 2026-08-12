<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\Customer;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegisterCustomer
{
    public function __construct(
        private readonly CustomerRegistration $registration,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data): Customer
    {
        if (! $this->registration->allowsRegistration()) {
            throw ValidationException::withMessages([
                'email' => __('customer.auth.registration_disabled'),
            ]);
        }

        $customer = DB::transaction(function () use ($data): Customer {
            return Customer::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        });

        event(new Registered($customer));

        return $customer;
    }
}
