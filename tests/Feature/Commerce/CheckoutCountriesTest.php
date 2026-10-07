<?php

declare(strict_types=1);

use App\Agovena\Checkout\CheckoutCountries;

test('checkout countries include worldwide iso codes with localized labels', function () {
    app()->setLocale('en');

    $options = CheckoutCountries::options();

    expect($options)->toHaveKey('NL')
        ->and($options)->toHaveKey('JP')
        ->and($options)->toHaveKey('BR')
        ->and($options['NL'])->toBe('Netherlands')
        ->and($options['JP'])->toBe('Japan')
        ->and(CheckoutCountries::isValid('XX'))->toBeFalse()
        ->and(count($options))->toBeGreaterThan(100);
});

test('checkout country labels follow the app locale', function () {
    app()->setLocale('nl');

    expect(CheckoutCountries::options()['NL'])->toBe('Nederland');
});
