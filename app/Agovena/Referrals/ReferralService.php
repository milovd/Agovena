<?php

declare(strict_types=1);

namespace App\Agovena\Referrals;

use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Models\Order;
use App\Models\ReferralAttribution;
use App\Models\ReferralCode;
use App\Models\ReferralVisit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReferralService
{
    public const DEFAULT_REWARD_PERCENTAGE = 10;

    public const DEFAULT_WINDOW_DAYS = 30;

    public const TRACKING_COOKIE = 'agovena_referral';

    public const VISITOR_COOKIE = 'agovena_referral_visitor';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly CustomerCreditLedger $creditLedger,
    ) {}

    public function createCode(
        Customer $customer,
        string $code,
        ?int $maxUses = null,
        ?CarbonInterface $expiresAt = null,
        ?int $rewardPercentage = null,
        ?int $windowDays = null,
    ): ReferralCode {
        $normalized = strtoupper(trim($code));
        if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{2,63}$/', $normalized)) {
            throw ValidationException::withMessages(['code' => 'The referral code format is invalid.']);
        }
        $this->assertRewardPercentage($rewardPercentage);
        $this->assertWindowDays($windowDays);

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
            'reward_amount' => $this->legacyRewardAmount(),
            'reward_percentage' => $rewardPercentage,
            'window_days' => $windowDays,
            'reward_currency' => $rewardCurrency,
            'fraud_review_required' => filter_var(
                $this->settings->get('referrals', 'fraud_review_required', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ]);
    }

    public function recordVisit(string $code, string $visitorHash): ?ReferralVisit
    {
        $referral = $this->findActiveCode($code);
        if (! $referral instanceof ReferralCode) {
            return null;
        }

        $now = now();
        $trackingExpiresAt = $this->trackingExpiry($referral, $now);

        return DB::transaction(function () use ($referral, $visitorHash, $now, $trackingExpiresAt): ReferralVisit {
            $visit = ReferralVisit::query()
                ->where('referral_code_id', $referral->id)
                ->where('visitor_hash', $visitorHash)
                ->lockForUpdate()
                ->first();

            if ($visit instanceof ReferralVisit) {
                $attributes = [
                    'last_clicked_at' => $now,
                    'clicks_count' => $visit->clicks_count + 1,
                ];
                if ($visit->converted_at === null && $visit->expires_at?->isPast()) {
                    $attributes['expires_at'] = $trackingExpiresAt;
                }
                $visit->update($attributes);

                return $visit->fresh(['code']) ?? $visit;
            }

            return ReferralVisit::query()->create([
                'referral_code_id' => $referral->id,
                'visitor_hash' => $visitorHash,
                'clicks_count' => 1,
                'first_clicked_at' => $now,
                'last_clicked_at' => $now,
                'expires_at' => $trackingExpiresAt,
            ]);
        });
    }

    public function cookieValue(ReferralVisit $visit): string
    {
        return json_encode([
            'visit_id' => $visit->id,
            'code' => $visit->code?->code,
        ], JSON_THROW_ON_ERROR);
    }

    public function visitFromCookie(?string $cookie): ?ReferralVisit
    {
        if (! is_string($cookie) || trim($cookie) === '') {
            return null;
        }

        try {
            $payload = json_decode($cookie, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($payload) || ! is_numeric($payload['visit_id'] ?? null)) {
            return null;
        }

        $visit = ReferralVisit::query()->with('code')->find((int) $payload['visit_id']);
        $referral = $visit?->code;
        if (! $visit instanceof ReferralVisit || ! $referral instanceof ReferralCode) {
            return null;
        }
        if (strtoupper((string) ($payload['code'] ?? '')) !== $referral->code) {
            return null;
        }
        if ($visit->expires_at?->isPast() || ! $this->isAvailable($referral)) {
            return null;
        }

        return $visit;
    }

    public function attribute(Order $order, string $code, ?int $visitId = null): ?ReferralAttribution
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $normalized = strtoupper(trim($code));

        return DB::transaction(function () use ($order, $normalized, $visitId): ?ReferralAttribution {
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
            if ($order->customer_id !== null && $this->hasPriorPaidOrder($order->customer_id, $order->id)) {
                return null;
            }

            $visit = null;
            if ($visitId !== null) {
                $visit = ReferralVisit::query()->whereKey($visitId)->lockForUpdate()->first();
                if (! $visit instanceof ReferralVisit
                    || $visit->referral_code_id !== $referral->id
                    || $visit->expires_at?->isPast()
                    || ReferralAttribution::query()->where('referral_visit_id', $visit->id)->exists()
                ) {
                    return null;
                }
            }

            $usesLegacyFixedReward = $referral->reward_percentage === null && (int) $referral->reward_amount > 0;
            $rewardPercentage = $usesLegacyFixedReward
                ? null
                : ($referral->reward_percentage ?? $this->defaultRewardPercentage());
            $rewardAmount = $usesLegacyFixedReward
                ? max(0, (int) $referral->reward_amount)
                : $this->calculateRewardAmount($order, (int) $rewardPercentage);

            $attribution = ReferralAttribution::query()->create([
                'order_id' => $order->id,
                'referral_code_id' => $referral->id,
                'referral_visit_id' => $visit?->id,
                'referrer_customer_id' => $referral->customer_id,
                'referred_customer_id' => $order->customer_id,
                'code_snapshot' => $referral->code,
                'status' => 'pending',
                'reward_amount' => $rewardAmount,
                'reward_percentage' => $rewardPercentage,
                'reward_currency' => $referral->reward_currency,
                'fraud_review_required' => $referral->fraud_review_required,
                'clicked_at' => $visit?->last_clicked_at,
                'tracking_expires_at' => $visit?->expires_at,
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
            if ($locked->purchased_at === null) {
                $locked->update(['purchased_at' => now()]);
            }
            $this->markVisitConverted($locked);
            if (in_array($locked->status, ['posted', 'no_reward', 'review', 'void'], true)) {
                return null;
            }
            if ($locked->referred_customer_id !== null
                && $this->hasPriorPaidOrder($locked->referred_customer_id, $locked->order_id)
            ) {
                $locked->update(['status' => 'no_reward']);

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

    public function updateCode(
        ReferralCode $referral,
        ?int $maxUses = null,
        ?CarbonInterface $expiresAt = null,
        ?int $rewardPercentage = null,
        ?int $windowDays = null,
    ): ReferralCode {
        $this->assertRewardPercentage($rewardPercentage);
        $this->assertWindowDays($windowDays);

        $configuredMaxUses = $maxUses;
        if ($configuredMaxUses !== null && $configuredMaxUses < 1) {
            throw ValidationException::withMessages(['max_uses' => 'The referral use limit must be positive.']);
        }

        $referral->update([
            'max_uses' => $configuredMaxUses,
            'expires_at' => $expiresAt,
            'reward_amount' => 0,
            'reward_percentage' => $rewardPercentage,
            'window_days' => $windowDays,
        ]);

        return $referral->fresh() ?? $referral;
    }

    public function defaultRewardPercentage(): int
    {
        $configured = $this->settings->get('store', 'referral_reward_percentage', null);
        if ($configured === null) {
            $configured = $this->settings->get('referrals', 'reward_percentage', null);
        }

        if ($configured === null) {
            return self::DEFAULT_REWARD_PERCENTAGE;
        }

        return max(0, min(100, (int) $configured));
    }

    public function defaultWindowDays(): int
    {
        $configured = $this->settings->get('store', 'referral_window_days', null);
        if ($configured === null) {
            $configured = $this->settings->get('referrals', 'window_days', null);
        }

        if ($configured === null) {
            return self::DEFAULT_WINDOW_DAYS;
        }

        return max(1, min(365, (int) $configured));
    }

    public function windowDaysFor(ReferralCode $referral): int
    {
        return $referral->window_days ?? $this->defaultWindowDays();
    }

    public function linkFor(ReferralCode $referral): string
    {
        return route('referrals.visit', ['code' => $referral->code]);
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

    public function isEnabled(): bool
    {
        $configured = $this->settings->get('store', 'referrals_enabled', null);
        if ($configured === null) {
            $configured = $this->settings->get('referrals', 'enabled', true);
        }

        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }

    public function findActiveCode(string $code): ?ReferralCode
    {
        $referral = ReferralCode::query()
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();

        return $referral instanceof ReferralCode && $this->isAvailable($referral) ? $referral : null;
    }

    private function legacyRewardAmount(): int
    {
        if ($this->settings->get('store', 'referral_reward_percentage', null) !== null
            || $this->settings->get('referrals', 'reward_percentage', null) !== null
        ) {
            return 0;
        }

        return max(0, (int) $this->settings->get('referrals', 'reward_amount', 0));
    }

    private function calculateRewardAmount(Order $order, int $percentage): int
    {
        if ($percentage < 1) {
            return 0;
        }

        $baseAmount = max(0, (int) $order->subtotal_amount - (int) $order->discount_amount);

        return min($baseAmount, intdiv($baseAmount * $percentage, 100));
    }

    private function assertRewardPercentage(?int $percentage): void
    {
        if ($percentage !== null && ($percentage < 0 || $percentage > 100)) {
            throw ValidationException::withMessages([
                'reward_percentage' => 'The referral reward percentage must be between 0 and 100.',
            ]);
        }
    }

    private function assertWindowDays(?int $days): void
    {
        if ($days !== null && ($days < 1 || $days > 365)) {
            throw ValidationException::withMessages([
                'window_days' => 'The referral window must be between 1 and 365 days.',
            ]);
        }
    }

    private function isAvailable(ReferralCode $referral): bool
    {
        $expiresAt = $referral->expires_at;

        return $referral->is_active && (! $expiresAt instanceof CarbonInterface || ! $expiresAt->isPast());
    }

    private function trackingExpiry(ReferralCode $referral, CarbonInterface $now): CarbonInterface
    {
        $trackingExpiresAt = $now->copy()->addDays($this->windowDaysFor($referral));
        $codeExpiresAt = $referral->expires_at;

        return $codeExpiresAt instanceof CarbonInterface && $codeExpiresAt->lessThan($trackingExpiresAt)
            ? $codeExpiresAt
            : $trackingExpiresAt;
    }

    private function hasPriorPaidOrder(int $customerId, int $exceptOrderId): bool
    {
        return Order::query()
            ->where('customer_id', $customerId)
            ->whereKeyNot($exceptOrderId)
            ->whereHas('payment', static fn ($query) => $query->where('status', PaymentStatus::Paid->value))
            ->exists();
    }

    private function markVisitConverted(ReferralAttribution $attribution): void
    {
        if ($attribution->referral_visit_id === null) {
            return;
        }

        ReferralVisit::query()->whereKey($attribution->referral_visit_id)->update([
            'referred_customer_id' => $attribution->referred_customer_id,
            'converted_at' => $attribution->purchased_at ?? now(),
        ]);
    }
}
