<?php

declare(strict_types=1);

use App\Support\LocalizedCurrencyFormatter;

it('formats large minor-unit totals exactly in English and Dutch at zero two and six decimals', function (string $locale, string $currency, int $precision, int $minor, string $expected): void {
    $formatter = new LocalizedCurrencyFormatter($locale, $currency, $precision);
    $value = str_replace(["\u{00A0}", "\u{202F}"], ' ', $formatter->format($minor));

    expect($value)->toBe($expected);
})->with([
    ['en', 'JPY', 0, PHP_INT_MAX, '¥9,223,372,036,854,775,807'],
    ['nl', 'JPY', 0, 1234, 'JP¥ 1.234'],
    ['en', 'EUR', 2, 123456, '€1,234.56'],
    ['nl', 'EUR', 2, 123456, '€ 1.234,56'],
    ['en', 'EUR', 6, 9000000000000001, '€9,000,000,000.000001'],
    ['nl', 'EUR', 6, 9000000000000001, '€ 9.000.000.000,000001'],
]);
