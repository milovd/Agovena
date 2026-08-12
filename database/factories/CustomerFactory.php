<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Customer $customer): void {
            $attributes = $customer->getAttributes();
            $password = $attributes['password'] ?? null;
            $verifiedAt = $attributes['email_verified_at'] ?? false;

            foreach (['password', 'remember_token', 'email_verified_at', 'anonymized_at', 'deletion_requested_at'] as $legacy) {
                unset($customer->{$legacy});
            }

            if ($customer->user_id) {
                return;
            }

            $userState = [
                'name' => $customer->name,
                'email' => $customer->email,
            ];
            if (is_string($password) && $password !== '') {
                $userState['password'] = $password;
            }
            if ($verifiedAt === null) {
                $userState['email_verified_at'] = null;
            }

            $user = User::withoutEvents(fn (): User => User::factory()->create($userState));
            $customer->user_id = $user->id;
        });
    }

    public function unverified(): static
    {
        return $this->afterCreating(function (Customer $customer): void {
            $customer->user?->forceFill(['email_verified_at' => null])->save();
        });
    }
}
