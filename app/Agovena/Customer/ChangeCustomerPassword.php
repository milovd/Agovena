<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangeCustomerPassword
{
    public function handle(Customer $customer, string $currentPassword, string $newPassword): void
    {
        $user = $customer->user;
        if ($user === null || ! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('customer.profile.current_password_invalid'),
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
        ])->save();
    }
}
