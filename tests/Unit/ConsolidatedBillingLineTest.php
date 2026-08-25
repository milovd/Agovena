<?php

declare(strict_types=1);

use App\Agovena\Billing\ConsolidatedBillingLine;
use Carbon\CarbonImmutable;

function billingLine(int $unitAmount = 1999, int $periodDays = 30, int $daysAlreadyPaid = 0): ConsolidatedBillingLine
{
    return new ConsolidatedBillingLine(
        sourceType: 'subscription',
        sourceId: 1,
        customerId: 1,
        customerName: 'Billing Customer',
        customerEmail: 'billing@example.test',
        currency: 'EUR',
        gatewayId: 'manual',
        productId: 1,
        originOrderItemId: 1,
        label: 'Service',
        quantity: 1,
        unitAmount: $unitAmount,
        dueAt: CarbonImmutable::parse('2026-10-01 10:00:00'),
        nextPeriodEnd: CarbonImmutable::parse('2026-11-01 10:00:00'),
        periodDays: $periodDays,
        daysAlreadyPaid: $daysAlreadyPaid,
    );
}

test('consolidated billing prorates integer minor units with half-up rounding', function () {
    expect(billingLine(daysAlreadyPaid: 3)->billableUnitAmount())->toBe(1799)
        ->and(billingLine(unitAmount: 100, periodDays: 10, daysAlreadyPaid: 5)->billableUnitAmount())->toBe(50);
});

test('consolidated billing does not charge time that was already paid', function () {
    expect(billingLine(unitAmount: 100, periodDays: 10, daysAlreadyPaid: 10)->lineTotal())->toBe(0)
        ->and(billingLine(unitAmount: 100, periodDays: 10, daysAlreadyPaid: 12)->lineTotal())->toBe(0);
});

test('consolidated billing rejects invalid currency and period values', function () {
    expect(fn (): ConsolidatedBillingLine => billingLine(periodDays: 0))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): ConsolidatedBillingLine => new ConsolidatedBillingLine(
        sourceType: 'subscription',
        sourceId: 1,
        customerId: 1,
        customerName: 'Billing Customer',
        customerEmail: 'billing@example.test',
        currency: 'eur',
        gatewayId: 'manual',
        productId: 1,
        originOrderItemId: 1,
        label: 'Service',
        quantity: 1,
        unitAmount: 100,
        dueAt: CarbonImmutable::parse('2026-10-01 10:00:00'),
        nextPeriodEnd: CarbonImmutable::parse('2026-11-01 10:00:00'),
        periodDays: 30,
        daysAlreadyPaid: 0,
    ))->toThrow(InvalidArgumentException::class);
});
