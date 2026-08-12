<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateCustomerProfile
{
    /**
     * @param  array{name: string, email: string}  $data
     */
    public function handle(Customer $customer, array $data): Customer
    {
        $emailChanged = mb_strtolower($customer->email) !== mb_strtolower($data['email']);

        if ($emailChanged) {
            $taken = Customer::query()
                ->where('email', $data['email'])
                ->whereKeyNot($customer->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'email' => __('customer.profile.email_taken'),
                ]);
            }
        }

        return DB::transaction(function () use ($customer, $data, $emailChanged): Customer {
            $customer->name = $data['name'];
            $customer->email = $data['email'];

            if ($emailChanged) {
                $customer->email_verified_at = null;
            }

            $customer->save();

            if ($emailChanged) {
                $customer->sendEmailVerificationNotification();
            }

            return $customer->fresh() ?? $customer;
        });
    }
}
