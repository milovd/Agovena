<?php

use App\Agovena\Money\Money;
use App\Support\MoneyFormatter;

test('money stores integer minor units', function () {
    $money = Money::of(1999, 'eur');

    expect($money->amount)->toBe(1999)
        ->and($money->currency)->toBe('EUR');
});

test('money rejects negative amounts', function () {
    Money::of(-1, 'EUR');
})->throws(InvalidArgumentException::class);

test('money multiply and add stay integer', function () {
    $unit = Money::of(500, 'EUR');
    $line = $unit->multiply(3);
    $total = $line->add(Money::of(100, 'EUR'));

    expect($line->amount)->toBe(1500)
        ->and($total->amount)->toBe(1600);
});

test('money rejects currency mismatch', function () {
    Money::of(100, 'EUR')->add(Money::of(100, 'USD'));
})->throws(InvalidArgumentException::class);

test('money formatter converts major input to minor units without floats', function () {
    expect(MoneyFormatter::minorFromMajorInput('45', 'EUR'))->toBe(4500)
        ->and(MoneyFormatter::minorFromMajorInput('45,00', 'EUR'))->toBe(4500)
        ->and(MoneyFormatter::minorFromMajorInput('45.99', 'EUR'))->toBe(4599)
        ->and(MoneyFormatter::majorInputFromMinor(4500, 'EUR'))->toBe('45.00');
});
