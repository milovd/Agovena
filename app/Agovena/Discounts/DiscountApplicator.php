<?php

declare(strict_types=1);

namespace App\Agovena\Discounts;

use App\Agovena\Money\Money;
use App\Models\DiscountCode;
use Illuminate\Validation\ValidationException;

final class DiscountApplicator
{
    public function apply(?string $code, Money $subtotal, ?int $customerId = null): ?AppliedDiscount
    {
        $normalized = strtoupper(trim((string) $code));
        if ($normalized === '') {
            return null;
        }

        $discount = DiscountCode::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first();

        if ($discount === null || ! $discount->is_active) {
            $this->fail('discount_invalid');
        }

        if (($discount->starts_at !== null && now()->lt($discount->starts_at))
            || ($discount->ends_at !== null && now()->gt($discount->ends_at))) {
            $this->fail('discount_expired');
        }

        if ($discount->max_uses !== null && $discount->redemptions()->count() >= $discount->max_uses) {
            $this->fail('discount_exhausted');
        }

        if ($customerId !== null && $discount->max_uses_per_customer !== null
            && $discount->redemptions()->where('customer_id', $customerId)->count() >= $discount->max_uses_per_customer) {
            $this->fail('discount_customer_limit');
        }

        if ($subtotal->amount < $discount->min_subtotal_amount) {
            $this->fail('discount_minimum');
        }

        if ($discount->type === 'fixed') {
            if ($discount->currency !== null && strtoupper($discount->currency) !== $subtotal->currency) {
                $this->fail('discount_currency');
            }
            $amount = min($subtotal->amount, $discount->value);
        } elseif ($discount->type === 'percent') {
            $amount = intdiv(($subtotal->amount * min(100, $discount->value)) + 50, 100);
        } else {
            $this->fail('discount_invalid');
        }

        return new AppliedDiscount($discount, Money::of($amount, $subtotal->currency));
    }

    private function fail(string $key): never
    {
        throw ValidationException::withMessages([
            'discount_code' => __("storefront.errors.{$key}"),
        ]);
    }
}
