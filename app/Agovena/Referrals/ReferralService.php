<?php

declare(strict_types=1);

namespace App\Agovena\Referrals;

use App\Agovena\Settings\SettingsRepository;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ReferralAttribution;
use App\Models\ReferralCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReferralService
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    public function createCode(Customer $customer, string $code): ReferralCode
    {
        $normalized = strtoupper(trim($code));
        if (! preg_match('/^[A-Z0-9][A-Z0-9_-]{2,63}$/', $normalized)) {
            throw ValidationException::withMessages(['code' => 'The referral code format is invalid.']);
        }

        $existing = ReferralCode::query()->where('code', $normalized)->first();
        if ($existing instanceof ReferralCode && $existing->customer_id !== $customer->id) {
            throw ValidationException::withMessages(['code' => 'The referral code is already in use.']);
        }

        return $existing ?? ReferralCode::query()->create([
            'customer_id' => $customer->id,
            'code' => $normalized,
            'uses_count' => 0,
            'is_active' => true,
        ]);
    }

    public function attribute(Order $order, string $code): ?ReferralAttribution
    {
        if (! $this->settings->get('referrals', 'enabled', false)) {
            return null;
        }

        return DB::transaction(function () use ($order, $code): ReferralAttribution {
            $existing = ReferralAttribution::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing instanceof ReferralAttribution) {
                if ($existing->code_snapshot !== strtoupper(trim($code))) {
                    throw ValidationException::withMessages(['code' => 'The order already has a different referral attribution.']);
                }

                return $existing;
            }

            $referral = ReferralCode::query()
                ->where('code', strtoupper(trim($code)))
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if (! $referral instanceof ReferralCode) {
                throw ValidationException::withMessages(['code' => 'The referral code is invalid or inactive.']);
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
            ]);

            $order->forceFill(['referral_code' => $referral->code])->save();
            $referral->increment('uses_count');

            return $attribution;
        });
    }
}
