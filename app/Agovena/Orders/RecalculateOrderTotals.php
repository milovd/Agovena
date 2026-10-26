<?php

declare(strict_types=1);

namespace App\Agovena\Orders;

use App\Agovena\Discounts\DiscountApplicator;
use App\Agovena\Money\Money;
use App\Agovena\Payments\PaymentFeePolicy;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Tax\TaxCalculator;
use App\Agovena\Tax\TaxRateResolver;
use App\Models\DiscountCode;
use App\Models\DiscountRedemption;
use App\Models\Order;

/**
 * Recomputes the money of an unpaid order from its current lines with the same rules checkout
 * uses: discount code, tax, the shipping already charged, and the payment fee of its method.
 * The caller holds the order lock.
 */
final class RecalculateOrderTotals
{
    public function __construct(
        private readonly DiscountApplicator $discounts,
        private readonly TaxCalculator $taxes,
        private readonly TaxRateResolver $taxRates,
        private readonly PaymentFeePolicy $paymentFees,
        private readonly SettingsRepository $settings,
    ) {}

    public function handle(Order $locked): Order
    {
        $locked->load(['items', 'payment']);
        $currency = (string) $locked->currency;

        $subtotal = Money::of((int) $locked->items->sum('line_total_amount'), $currency);

        $discountAmount = 0;
        if (filled($locked->discount_code)) {
            $code = DiscountCode::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper((string) $locked->discount_code)])
                ->first();
            $discountAmount = $code !== null
                ? $this->discounts->amountFor($code, $subtotal)
                : min((int) $locked->discount_amount, $subtotal->amount);
        }

        $afterDiscount = Money::of($subtotal->amount - $discountAmount, $currency);
        $shipping = Money::of((int) $locked->shipping_amount, $currency);
        $country = (string) ($locked->shipping_country ?: ($locked->billing_country ?: 'NL'));
        $pricesIncludeTax = (bool) $this->settings->get('store', 'prices_include_tax', false);
        $tax = $this->taxes->calculate($afterDiscount, $shipping, $pricesIncludeTax, $this->taxRates->resolve($country));

        $total = $afterDiscount->amount + $shipping->amount + ($pricesIncludeTax ? 0 : $tax->tax->amount);
        $credit = (int) $locked->credit_amount;

        $feeAmount = 0;
        $feeSnapshot = $locked->payment_fee_snapshot;
        if ($locked->payment !== null && filled($locked->payment->method)) {
            $fee = $this->paymentFees->calculate((string) $locked->payment->method, Money::of(max(0, $total - $credit), $currency));
            $feeAmount = $fee->amount;
            $feeSnapshot = $fee->snapshot;
        }

        $locked->forceFill([
            'subtotal_amount' => $subtotal->amount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $tax->tax->amount,
            'tax_rate_name' => $tax->rateName,
            'tax_rate_bps' => $tax->rateBps,
            'payment_fee_amount' => $feeAmount,
            'payment_fee_snapshot' => $feeSnapshot,
            'total_amount' => $total + $feeAmount,
        ])->save();

        $locked->payment?->forceFill(['amount' => $total + $feeAmount - $credit])->save();

        DiscountRedemption::query()->where('order_id', $locked->id)->update(['amount' => $discountAmount]);

        return $locked;
    }
}
