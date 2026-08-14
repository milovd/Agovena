<?php

use App\Agovena\Payments\Gateways\DevelopmentPaymentGateway;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Agovena\Payments\PaymentGatewayRegistry;
use Tests\Support\ProviderContracts\ProviderContractAssertions;

test('core application and modules do not import first-party provider extension namespaces', function () {
    $roots = [base_path('app'), base_path('modules'), base_path('themes')];
    $needles = [
        'Agovena\\Extensions\\Mollie\\',
        'Agovena\\Extensions\\Stripe\\',
        'Agovena\\Extensions\\Postnl\\',
        'Agovena\\Extensions\\Pterodactyl\\',
        'Mollie\\Api\\',
        'Stripe\\Webhook',
        'api.postnl.nl',
    ];
    $violations = [];

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            foreach ($needles as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()).' ['.$needle.']';
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

test('core checkout does not branch on payment extension identifiers', function () {
    $checkout = (string) file_get_contents(base_path('app/Livewire/Storefront/CheckoutPage.php'));

    expect($checkout)->not->toContain("=== 'mollie'")
        ->and($checkout)->not->toContain('=== "mollie"')
        ->and($checkout)->not->toContain("=== 'stripe'")
        ->and($checkout)->not->toContain('=== "stripe"');
});

test('core payment gateways satisfy the provider contract kit', function () {
    ProviderContractAssertions::assertPaymentGateway(new ManualPaymentGateway);
    ProviderContractAssertions::assertPaymentGateway(app(DevelopmentPaymentGateway::class));
    expect(app(PaymentGatewayRegistry::class))->toBeInstanceOf(PaymentGatewayRegistry::class);
});
