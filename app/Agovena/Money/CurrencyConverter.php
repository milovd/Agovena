<?php

declare(strict_types=1);

namespace App\Agovena\Money;

use App\Agovena\Settings\SettingsRepository;
use App\Models\Currency;
use InvalidArgumentException;

/**
 * Converts integer minor-unit amounts between currencies using admin-managed rates.
 *
 * Rate semantics: units of this currency per 1 unit of the shop base currency.
 * Example with base EUR: USD exchange_rate 1.08 means 1 EUR = 1.08 USD.
 *
 * Uses BCMath only - no floats.
 */
final class CurrencyConverter
{
    public function __construct(
        private readonly CurrencyCatalog $catalog,
        private readonly SettingsRepository $settings,
    ) {}

    public function convert(int $amount, string $from, string $to): int
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return $amount;
        }

        $fromCurrency = $this->requireCurrency($from);
        $toCurrency = $this->requireCurrency($to);

        $fromRate = $this->normalizedRate($fromCurrency);
        $toRate = $this->normalizedRate($toCurrency);
        $fromPrecision = $fromCurrency->normalizedPrecision();
        $toPrecision = $toCurrency->normalizedPrecision();

        $fromScale = bcpow('10', (string) $fromPrecision, 0);
        $toScale = bcpow('10', (string) $toPrecision, 0);

        $fromMajor = bcdiv((string) $amount, $fromScale, 12);
        $baseMajor = bcdiv($fromMajor, $fromRate, 12);
        $toMajor = bcmul($baseMajor, $toRate, 12);
        $scaled = bcmul($toMajor, $toScale, 8);

        return $this->roundHalfUpToInt($scaled);
    }

    public function convertMoney(Money $money, string $to): Money
    {
        return Money::of(
            $this->convert($money->amount, $money->currency, $to),
            $to,
        );
    }

    public function baseCurrencyCode(): string
    {
        try {
            $base = strtoupper((string) $this->settings->get('general', 'base_currency', 'EUR'));
            if ($base !== '' && $this->catalog->find($base) !== null) {
                return $base;
            }
        } catch (\Throwable) {
            // Fall through.
        }

        return 'EUR';
    }

    private function requireCurrency(string $code): Currency
    {
        $currency = $this->catalog->find($code);
        if ($currency === null) {
            throw new InvalidArgumentException("Unknown currency [{$code}].");
        }

        return $currency;
    }

    private function normalizedRate(Currency $currency): string
    {
        $raw = (string) ($currency->exchange_rate ?? '1');
        $raw = trim($raw);
        if ($raw === '' || ! is_numeric($raw) || bccomp($raw, '0', 8) !== 1) {
            return '1.00000000';
        }

        return bcadd($raw, '0', 8);
    }

    private function roundHalfUpToInt(string $scaled): int
    {
        if (bccomp($scaled, '0', 8) === -1) {
            return 0;
        }

        $whole = bcadd($scaled, '0', 0);
        $fraction = bcsub($scaled, $whole, 8);

        if (bccomp($fraction, '0.50000000', 8) >= 0) {
            $whole = bcadd($whole, '1', 0);
        }

        return (int) $whole;
    }
}
