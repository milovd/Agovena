<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateCustomerProfile
{
    /**
     * @param  array{name: string, email: string}  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        $user = $customer->user;
        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => __('customer.profile.email_taken'),
            ]);
        }

        $emailChanged = mb_strtolower($user->email) !== mb_strtolower($data['email']);

        if ($emailChanged) {
            $taken = User::query()
                ->where('email', $data['email'])
                ->whereKeyNot($user->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'email' => __('customer.profile.email_taken'),
                ]);
            }
        }

        return DB::transaction(function () use ($user, $customer, $data, $emailChanged): Customer {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            $customer->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
            ])->save();

            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }

            return $customer->fresh() ?? $customer;
        });
    }
}
