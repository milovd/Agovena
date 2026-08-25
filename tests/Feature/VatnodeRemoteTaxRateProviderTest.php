<?php

use App\Agovena\Tax\VatnodeRemoteTaxRateProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('vatnode remote provider maps standard percents to basis points', function () {
    Cache::flush();
    Http::fake([
        'cdn.jsdelivr.net/*' => Http::response([
            'version' => '2026-08-25-test',
            'rates' => [
                'NL' => ['standard' => 21.0],
                'DE' => ['standard' => 19.0],
                'FI' => ['standard' => 25.5],
            ],
        ], 200),
    ]);

    config(['agovena.tax.automatic_provider' => 'vatnode']);
    $provider = app(VatnodeRemoteTaxRateProvider::class);

    expect($provider->standardRateBps('NL'))->toBe(2100)
        ->and($provider->standardRateBps('DE'))->toBe(1900)
        ->and($provider->standardRateBps('FI'))->toBe(2550)
        ->and($provider->standardRateBps('US'))->toBeNull()
        ->and($provider->rateName('NL'))->toBe('NL VAT')
        ->and($provider->version())->toBe('2026-08-25-test')
        ->and($provider->sourceLabel())->toContain('vatnode');

    Http::assertSentCount(1);
});
