<?php

declare(strict_types=1);

namespace App\Agovena\Money;

use InvalidArgumentException;

/**
 * Integer minor-unit money. No floats.
 */
final readonly class Money
{
    public int $amount;

    public string $currency;

    public function __construct(int $amount, string $currency)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }

        $normalized = strtoupper(trim($currency));

        if (strlen($normalized) !== 3 || ! ctype_alpha($normalized)) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }

        $this->amount = $amount;
        $this->currency = $normalized;
    }

    public static function of(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(max(0, $this->amount - $other->amount), $this->currency);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        return new self($this->amount * $quantity, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
    }
}
