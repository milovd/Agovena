<?php

declare(strict_types=1);

namespace App\Agovena\Privacy;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Auth\ManageUserSessions;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\ProductPlanChangeRequest;
use App\Models\ReferralAttribution;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteCustomerAccount
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ManageUserSessions $sessions,
    ) {}

    /**
     * @return list<string>
     */
    public function blockingReasons(Customer $customer): array
    {
        $reasons = [];

        if ($customer->orders()->exists() || $customer->invoices()->exists() || $customer->creditNotes()->exists()) {
            $reasons[] = __('admin.customers.full_delete_statutory_records');
        }

        if ($customer->creditEntries()->exists() || $this->hasNonEmptyCreditAccount($customer)) {
            $reasons[] = __('admin.customers.full_delete_credit_records');
        }

        if (ProductPlanChangeRequest::query()->where('customer_id', $customer->id)->exists()) {
            $reasons[] = __('admin.customers.full_delete_plan_records');
        }

        if (ReferralAttribution::query()->where('referrer_customer_id', $customer->id)->exists()) {
            $reasons[] = __('admin.customers.full_delete_referral_records');
        }

        $customer->loadMissing('user');
        if ($customer->user?->canAccessAdmin()) {
            $reasons[] = __('admin.customers.full_delete_admin_account');
        }

        return $reasons;
    }

    public function handle(Customer $customer): void
    {
        $reasons = $this->blockingReasons($customer);
        if ($reasons !== []) {
            throw ValidationException::withMessages([
                'fullDelete' => implode(' ', $reasons),
            ]);
        }

        $customerId = $customer->id;
        $userId = $customer->user_id;

        DB::transaction(function () use ($customer, $customerId): void {
            $customer->loadMissing('user');
            if ($customer->user !== null) {
                $this->sessions->revokeAll($customer->user);
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $customer->user->id)
                    ->delete();
                DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('model_id', $customer->user->id)
                    ->delete();
                DB::table('model_has_permissions')
                    ->where('model_type', User::class)
                    ->where('model_id', $customer->user->id)
                    ->delete();
            }
            foreach ($customer->tickets()->get() as $ticket) {
                if ($ticket instanceof Ticket) {
                    $ticket->delete();
                }
            }
            $customer->addresses()->delete();
            $customer->propertyValues()->delete();
            $customer->referralCodes()->delete();

            // Ledger rows are immutable during normal operation; full erasure is the explicit exception.
            DB::table('customer_credit_entries')->where('customer_id', $customerId)->delete();
            DB::table('customer_credit_accounts')->where('customer_id', $customerId)->delete();

            $customer->delete();
            $customer->user?->delete();
        });

        $this->audit->log('customer.deleted', null, [
            'customer_id' => $customerId,
            'user_id' => $userId,
        ]);
    }

    private function hasNonEmptyCreditAccount(Customer $customer): bool
    {
        $account = $customer->creditAccount()->first();
        if (! $account instanceof CustomerCreditAccount) {
            return false;
        }

        return (int) $account->balance_amount !== 0 || (int) $account->reserved_amount !== 0;
    }
}
