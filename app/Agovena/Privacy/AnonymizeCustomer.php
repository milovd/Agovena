<?php

declare(strict_types=1);

namespace App\Agovena\Privacy;

use App\Agovena\Audit\AuditLogger;
use App\Enums\TicketStatus;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

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

            $customer->forceFill([
                'name' => __('customer.privacy.deleted_customer'),
                'email' => 'deleted+'.$customer->id.'@anonymized.invalid',
                'password' => null,
                'remember_token' => null,
                'email_verified_at' => null,
                'anonymized_at' => now(),
            ])->save();

            return $customer->fresh() ?? $customer;
        });

        $this->audit->log('customer.anonymized', $customer);

        return $customer;
    }
}
