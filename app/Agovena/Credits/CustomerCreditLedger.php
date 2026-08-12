<?php

declare(strict_types=1);

namespace App\Agovena\Credits;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\CustomerCreditEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerCreditLedger
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function balance(Customer $customer, ?string $currency = null): int
    {
        $account = CustomerCreditAccount::query()->where('customer_id', $customer->id)->first();
        if ($account === null || ($currency !== null && $account->currency !== strtoupper($currency))) {
            return 0;
        }

        return $account->balance_amount;
    }

    public function credit(
        Customer $customer,
        int $amount,
        string $reason,
        ?Model $reference = null,
        ?User $staff = null,
        array $meta = [],
    ): CustomerCreditEntry {
        return $this->record($customer, 'credit', $amount, $reason, $reference, $staff, $meta);
    }

    public function debit(
        Customer $customer,
        int $amount,
        string $reason,
        ?Model $reference = null,
        ?User $staff = null,
        array $meta = [],
    ): CustomerCreditEntry {
        return $this->record($customer, 'debit', $amount, $reason, $reference, $staff, $meta);
    }

    private function record(
        Customer $customer,
        string $type,
        int $amount,
        string $reason,
        ?Model $reference,
        ?User $staff,
        array $meta,
    ): CustomerCreditEntry {
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => __('admin.customers.credit_amount_positive')]);
        }

        $entry = DB::transaction(function () use ($customer, $type, $amount, $reason, $reference, $staff, $meta): CustomerCreditEntry {
            $account = CustomerCreditAccount::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $account = CustomerCreditAccount::query()->create([
                    'customer_id' => $customer->id,
                    'currency' => strtoupper((string) $this->settings->get('general', 'base_currency', 'EUR')),
                    'balance_amount' => 0,
                ]);
                $account = CustomerCreditAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            }

            if ($type === 'debit' && $amount > $account->balance_amount) {
                throw ValidationException::withMessages(['amount' => __('admin.customers.credit_overdraft')]);
            }

            $balanceAfter = $type === 'credit'
                ? $account->balance_amount + $amount
                : $account->balance_amount - $amount;

            $account->update(['balance_amount' => $balanceAfter]);

            return CustomerCreditEntry::query()->create([
                'customer_id' => $customer->id,
                'entry_type' => $type,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reason' => trim($reason),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'staff_user_id' => $staff?->id,
                'meta' => $meta === [] ? null : $meta,
            ]);
        });

        if ($staff !== null) {
            $this->audit->log('customer_credit.adjusted', $customer, [
                'entry_type' => $type,
                'amount' => $amount,
                'balance_after' => $entry->balance_after,
                'reason' => $reason,
            ]);
        }

        return $entry;
    }
}
