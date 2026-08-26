<?php

declare(strict_types=1);

namespace App\Agovena\Referrals;

use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Models\Order;
use App\Models\ReferralAttribution;
use App\Models\ReferralCode;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReferralService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly CustomerCreditLedger $creditLedger,
    ) {}

    public function createCode(
        Customer $customer,
        string $code,
        ?int $maxUses = null,
        ?CarbonInterface $expiresAt = null,
    ): ReferralCode {
        $normalized = strtoupper(trim($code));
        if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{2,63}$/', $normalized)) {
            throw ValidationException::withMessages(['code' => 'The referral code format is invalid.']);
        }

        $existing = ReferralCode::query()->where('code', $normalized)->first();
        if ($existing instanceof ReferralCode && $existing->customer_id !== $customer->id) {
            throw ValidationException::withMessages(['code' => 'The referral code is already in use.']);
        }
        if ($existing instanceof ReferralCode) {
            return $existing;
        }

        $configuredMaxUses = $maxUses ?? $this->settings->get('referrals', 'max_uses', null);
        $configuredMaxUses = $configuredMaxUses === null ? null : (int) $configuredMaxUses;
        if ($configuredMaxUses !== null && $configuredMaxUses < 1) {
            throw ValidationException::withMessages(['max_uses' => 'The referral use limit must be positive.']);
        }

        $rewardCurrency = strtoupper((string) $this->settings->get(
            'referrals',
            'reward_currency',
            $this->settings->get('general', 'base_currency', 'EUR'),
        ));

        return ReferralCode::query()->create([
            'customer_id' => $customer->id,
            'code' => $normalized,
            'uses_count' => 0,
            'is_active' => true,
            'max_uses' => $configuredMaxUses,
            'expires_at' => $expiresAt,
            'reward_amount' => max(0, (int) $this->settings->get('referrals', 'reward_amount', 0)),
            'reward_currency' => $rewardCurrency,
            'fraud_review_required' => filter_var(
                $this->settings->get('referrals', 'fraud_review_required', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ]);
    }

    public function attribute(Order $order, string $code): ?ReferralAttribution
    {
        if (! filter_var($this->settings->get('referrals', 'enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return DB::transaction(function () use ($order, $normalized): ReferralAttribution {
            $existing = ReferralAttribution::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing instanceof ReferralAttribution) {
                if ($existing->code_snapshot !== $normalized) {
                    throw ValidationException::withMessages(['code' => 'The order already has a different referral attribution.']);
                }

                return $existing;
            }

            $referral = ReferralCode::query()
                ->where('code', $normalized)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if (! $referral instanceof ReferralCode) {
                throw ValidationException::withMessages(['code' => 'The referral code is invalid or inactive.']);
            }
            $expiresAt = $referral->getAttribute('expires_at');
            if ($expiresAt instanceof CarbonInterface && $expiresAt->isPast()) {
                throw ValidationException::withMessages(['code' => 'The referral code has expired.']);
            }
            $maxUses = $referral->getAttribute('max_uses');
            if ($maxUses !== null && $referral->uses_count >= (int) $maxUses) {
                throw ValidationException::withMessages(['code' => 'The referral code has reached its use limit.']);
            }
            if ($order->customer_id !== null && $order->customer_id === $referral->customer_id) {
                throw ValidationException::withMessages(['code' => 'A customer cannot refer their own order.']);
            }

            $attribution = ReferralAttribution::query()->create([
                'order_id' => $order->id,
                'referral_code_id' => $referral->id,
                'referrer_customer_id' => $referral->customer_id,
                'referred_customer_id' => $order->customer_id,
                'code_snapshot' => $referral->code,
                'status' => 'pending',
                'reward_amount' => $referral->reward_amount,
                'reward_currency' => $referral->reward_currency,
                'fraud_review_required' => $referral->fraud_review_required,
            ]);

            $order->forceFill(['referral_code' => $referral->code])->save();
            $referral->increment('uses_count');

            return $attribution;
        });
    }

    public function settle(Order $order): ?CustomerCreditEntry
    {
        $attribution = ReferralAttribution::query()->where('order_id', $order->id)->first();
        if (! $attribution instanceof ReferralAttribution) {
            return null;
        }

        return DB::transaction(function () use ($attribution): ?CustomerCreditEntry {
            $locked = ReferralAttribution::query()->whereKey($attribution->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['posted', 'no_reward', 'review', 'void'], true)) {
                return null;
            }
            if ($locked->reward_amount < 1) {
                $locked->update(['status' => 'no_reward']);

                return null;
            }
            if ($locked->fraud_review_required) {
                $locked->update(['status' => 'review']);

                return null;
            }

            $referrer = Customer::query()->findOrFail($locked->referrer_customer_id);
            $baseCurrency = strtoupper((string) $this->settings->get('general', 'base_currency', 'EUR'));
            if ($locked->reward_currency !== null && strtoupper($locked->reward_currency) !== $baseCurrency) {
                $locked->update(['status' => 'review']);

                return null;
            }

            $entry = $this->creditLedger->credit(
                $referrer,
                $locked->reward_amount,
                'referral_reward',
                $locked,
                null,
                ['order_id' => $locked->order_id, 'code' => $locked->code_snapshot],
            );
            $locked->update(['status' => 'posted', 'credited_at' => now()]);

            return $entry;
        });
    }

    public function approveReview(ReferralAttribution $attribution): ?CustomerCreditEntry
    {
        $attribution->update([
            'fraud_review_required' => false,
            'status' => 'pending',
            'reviewed_at' => now(),
        ]);

        return $this->settle(Order::query()->findOrFail($attribution->order_id));
    }

    public function rejectReview(ReferralAttribution $attribution): void
    {
        $attribution->update(['status' => 'void', 'reviewed_at' => now()]);
    }
}
