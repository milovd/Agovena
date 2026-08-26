<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps first-party payment extensions discoverable from the monorepo catalog', function (): void {
    $root = config('agovena.packages.optional_packages_path');
    $catalog = config('agovena.packages.monorepo.packages');

    expect($root)->toBeString()->not->toBe('');

    foreach ([
        'mollie' => 'extensions/payments/mollie',
        'stripe' => 'extensions/payments/stripe',
        'paypal' => 'extensions/payments/paypal',
        'paddle' => 'extensions/payments/paddle',
        'tebex' => 'extensions/payments/tebex',
    ] as $id => $path) {
        expect($catalog[$id] ?? null)->toBe([
            'kind' => 'extension',
            'path' => $path,
        ]);

        $manifestPath = $root.'/'.$path.'/extension.json';
        expect(File::exists($manifestPath))->toBeTrue("Missing manifest for {$id}");

        $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        expect($manifest['id'] ?? null)->toBe($id)
            ->and($manifest['category'] ?? null)->toBe('payment_gateway')
            ->and($manifest['provider'] ?? null)->toContain('Agovena\\Extensions\\');
    }
});
