<?php

declare(strict_types=1);

namespace App\Agovena\Credits;

use App\Agovena\Audit\AuditLogger;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\CustomerCreditEntry;
use App\Models\Order;
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

    /**
     * Total owned balance (includes amounts reserved for unpaid orders).
     */
    public function balance(Customer $customer, ?string $currency = null): int
    {
        $account = $this->account($customer, $currency);
        if ($account === null) {
            return 0;
        }

        return (int) $account->balance_amount;
    }

    /**
     * Spendable balance after reservations for unpaid orders.
     */
    public function available(Customer $customer, ?string $currency = null): int
    {
        $account = $this->account($customer, $currency);
        if ($account === null) {
            return 0;
        }

        return max(0, (int) $account->balance_amount - (int) $account->reserved_amount);
    }

    public function reserved(Customer $customer, ?string $currency = null): int
    {
        $account = $this->account($customer, $currency);
        if ($account === null) {
            return 0;
        }

        return (int) $account->reserved_amount;
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

    /**
     * Hold account balance for an unpaid order. Available balance drops; owned balance stays until capture.
     */
    public function reserve(Customer $customer, int $amount, Order $order): void
    {
        if ($amount < 1) {
            return;
        }

        DB::transaction(function () use ($customer, $amount, $order): void {
            $account = $this->lockedAccount($customer, $order->currency);

            $available = (int) $account->balance_amount - (int) $account->reserved_amount;
            if ($amount > $available) {
                throw ValidationException::withMessages(['amount' => __('admin.customers.credit_overdraft')]);
            }

            $account->update([
                'reserved_amount' => (int) $account->reserved_amount + $amount,
            ]);
        });
    }

    /**
     * Finalize a reservation after the order is paid (debit owned balance + clear hold).
     */
    public function capture(Customer $customer, int $amount, Order $order): ?CustomerCreditEntry
    {
        if ($amount < 1) {
            return null;
        }

        return DB::transaction(function () use ($customer, $amount, $order): CustomerCreditEntry {
            $account = $this->lockedAccount($customer, $order->currency);

            if ($amount > (int) $account->reserved_amount) {
                throw ValidationException::withMessages(['amount' => __('admin.customers.credit_overdraft')]);
            }

            if ($amount > (int) $account->balance_amount) {
                throw ValidationException::withMessages(['amount' => __('admin.customers.credit_overdraft')]);
            }

            $balanceAfter = (int) $account->balance_amount - $amount;
            $account->update([
                'balance_amount' => $balanceAfter,
                'reserved_amount' => (int) $account->reserved_amount - $amount,
            ]);

            return CustomerCreditEntry::query()->create([
                'customer_id' => $customer->id,
                'entry_type' => 'debit',
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reason' => 'order_credit',
                'reference_type' => $order->getMorphClass(),
                'reference_id' => $order->getKey(),
                'staff_user_id' => null,
                'meta' => ['phase' => 'capture'],
            ]);
        });
    }

    /**
     * Release a reservation when an unpaid order is cancelled.
     */
    public function release(Customer $customer, int $amount, Order $order): void
    {
        if ($amount < 1) {
            return;
        }

        DB::transaction(function () use ($customer, $amount, $order): void {
            $account = $this->lockedAccount($customer, $order->currency);
            $release = min($amount, (int) $account->reserved_amount);
            if ($release < 1) {
                return;
            }

            $account->update([
                'reserved_amount' => (int) $account->reserved_amount - $release,
            ]);
        });
    }

    private function account(Customer $customer, ?string $currency): ?CustomerCreditAccount
    {
        $account = CustomerCreditAccount::query()->where('customer_id', $customer->id)->first();
        if ($account === null || ($currency !== null && $account->currency !== strtoupper($currency))) {
            return null;
        }

        return $account;
    }

    private function lockedAccount(Customer $customer, string $currency): CustomerCreditAccount
    {
        $account = CustomerCreditAccount::query()
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            $account = CustomerCreditAccount::query()->create([
                'customer_id' => $customer->id,
                'currency' => strtoupper($currency),
                'balance_amount' => 0,
                'reserved_amount' => 0,
            ]);
            $account = CustomerCreditAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
        }

        if ($account->currency !== strtoupper($currency)) {
            throw ValidationException::withMessages(['amount' => __('admin.customers.credit_overdraft')]);
        }

        return $account;
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
            $currency = strtoupper((string) $this->settings->get('general', 'base_currency', 'EUR'));
            $account = CustomerCreditAccount::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $account = CustomerCreditAccount::query()->create([
                    'customer_id' => $customer->id,
                    'currency' => $currency,
                    'balance_amount' => 0,
                    'reserved_amount' => 0,
                ]);
                $account = CustomerCreditAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            }

            $available = (int) $account->balance_amount - (int) $account->reserved_amount;
            if ($type === 'debit' && $amount > $available) {
                throw ValidationException::withMessages(['amount' => __('admin.customers.credit_overdraft')]);
            }

            $balanceAfter = $type === 'credit'
                ? (int) $account->balance_amount + $amount
                : (int) $account->balance_amount - $amount;

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
