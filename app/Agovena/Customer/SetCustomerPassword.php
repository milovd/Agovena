<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Agovena\Audit\AuditLogger;
use App\Models\Customer;
use Illuminate\Validation\ValidationException;

final class SetCustomerPassword
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Customer $customer, string $password): void
    {
        $user = $customer->user;
        if ($user === null) {
            throw ValidationException::withMessages([
                'password' => __('admin.customers.password_no_user'),
            ]);
        }

        $user->forceFill([
            'password' => $password,
            'remember_token' => null,
        ])->save();

        $this->audit->log('customer.password_changed', $customer);
    }
}
