<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\User;
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
    public function handle(array $data): User
    {
        if (! $this->registration->allowsRegistration()) {
            throw ValidationException::withMessages([
                'email' => __('customer.auth.registration_disabled'),
            ]);
        }

        $user = DB::transaction(function () use ($data): User {
            return User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        });

        event(new Registered($user));

        return $user;
    }
}
