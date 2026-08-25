<?php

declare(strict_types=1);

namespace App\Agovena\Billing;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ConsolidatedBillingLine
{
    /**
     * @param  array<string, mixed>  $optionsSnapshot
     */
    public function __construct(
        public string $sourceType,
        public int $sourceId,
        public ?int $customerId,
        public string $customerName,
        public string $customerEmail,
        public string $currency,
        public ?string $gatewayId,
        public ?int $productId,
        public ?int $originOrderItemId,
        public string $label,
        public int $quantity,
        public int $unitAmount,
        public CarbonImmutable $dueAt,
        public CarbonImmutable $nextPeriodEnd,
        public int $periodDays,
        public int $daysAlreadyPaid,
        public array $optionsSnapshot = [],
    ) {
        if ($this->sourceType === '' || $this->sourceId < 1) {
            throw new InvalidArgumentException('A billing source must have a type and positive id.');
        }

        if ($this->quantity < 1 || $this->unitAmount < 0) {
            throw new InvalidArgumentException('Billing quantity and amount must be valid.');
        }

        if ($this->periodDays < 1) {
            throw new InvalidArgumentException('Billing period must contain at least one day.');
        }

        if ($this->daysAlreadyPaid < 0) {
            throw new InvalidArgumentException('Days already paid cannot be negative.');
        }

        if (strtoupper(trim($this->currency)) !== $this->currency || strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Billing currency must be an uppercase ISO code.');
        }
    }

    public function billableUnitAmount(): int
    {
        if ($this->daysAlreadyPaid === 0) {
            return $this->unitAmount;
        }

        $billableDays = max(0, $this->periodDays - $this->daysAlreadyPaid);

        return intdiv(
            ($this->unitAmount * $billableDays) + intdiv($this->periodDays, 2),
            $this->periodDays,
        );
    }

    public function lineTotal(): int
    {
        return $this->billableUnitAmount() * $this->quantity;
    }
}
