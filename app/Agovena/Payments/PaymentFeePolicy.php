<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Money\Money;
use App\Agovena\Settings\SettingsRepository;
use InvalidArgumentException;

final class PaymentFeePolicy
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function calculate(string $gatewayId, Money $amount): PaymentFee
    {
        $gatewayId = trim($gatewayId);
        $rules = $this->settings->get('payments', 'gateway_fee_rules', []);
        $rule = is_array($rules) && is_array($rules[$gatewayId] ?? null) ? $rules[$gatewayId] : null;

        if ($rule === null || ! $this->toBool($rule['enabled'] ?? false)) {
            return new PaymentFee(0, $amount->currency, [
                'gateway_id' => $gatewayId,
                'enabled' => false,
                'percentage_bps' => 0,
                'fixed_amount' => 0,
                'currency' => $amount->currency,
            ]);
        }

        $currency = strtoupper(trim((string) ($rule['currency'] ?? $amount->currency)));
        if ($currency !== $amount->currency) {
            throw new InvalidArgumentException('Payment fee currency must match the order currency.');
        }

        $percentageBps = (int) ($rule['percentage_bps'] ?? 0);
        $fixedAmount = (int) ($rule['fixed_amount'] ?? 0);
        if ($percentageBps < 0 || $percentageBps > 10_000 || $fixedAmount < 0) {
            throw new InvalidArgumentException('Payment fee rule contains an invalid amount.');
        }

        $percentageAmount = $percentageBps === 0
            ? 0
            : intdiv(($amount->amount * $percentageBps) + 9_999, 10_000);

        return new PaymentFee(
            $percentageAmount + $fixedAmount,
            $amount->currency,
            [
                'gateway_id' => $gatewayId,
                'enabled' => true,
                'percentage_bps' => $percentageBps,
                'fixed_amount' => $fixedAmount,
                'currency' => $amount->currency,
            ],
        );
    }

    private function toBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
