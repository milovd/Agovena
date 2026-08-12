<?php

declare(strict_types=1);

namespace App\Agovena\Privacy;

use App\Agovena\Audit\AuditLogger;
use App\Enums\TicketStatus;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AnonymizeCustomer
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Customer $customer): Customer
    {
        if ($customer->anonymized_at !== null) {
            return $customer;
        }

        $customer = DB::transaction(function () use ($customer): Customer {
            $customer->addresses()->delete();
            $customer->tickets()
                ->where('status', '!=', TicketStatus::Closed->value)
                ->update(['status' => TicketStatus::Closed->value]);

            $email = 'deleted+'.$customer->id.'@anonymized.invalid';
            $name = __('customer.privacy.deleted_customer');

            $user = $customer->user;
            if ($user !== null) {
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::password(32)),
                    'remember_token' => null,
                    'email_verified_at' => null,
                    'anonymized_at' => now(),
                    'deletion_requested_at' => $user->deletion_requested_at ?? now(),
                ])->save();
            } else {
                $customer->forceFill([
                    'name' => $name,
                    'email' => $email,
                ])->save();
            }

            return $customer->fresh() ?? $customer;
        });

        $this->audit->log('customer.anonymized', $customer);

        return $customer;
    }
}
