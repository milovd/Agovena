<?php

declare(strict_types=1);

use Agovena\Extensions\NamecheapRegistrar\NamecheapApi;
use Agovena\Extensions\NamecheapRegistrar\NamecheapRegistrar;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Extensions\ExtensionManager;
use Illuminate\Support\Facades\File;

it('discovers the Namecheap Registrar extension with a domains dependency', function (): void {
    $root = config('agovena.packages.optional_packages_path');
    $catalog = config('agovena.packages.monorepo.packages');
    $discovered = app(ExtensionManager::class)->discover();
    $manifest = collect($discovered)->firstWhere('id', 'namecheap-registrar');

    expect($catalog['namecheap-registrar'] ?? null)->toBe([
        'kind' => 'extension',
        'path' => 'extensions/domains/namecheap-registrar',
    ])
        ->and(File::exists($root.'/extensions/domains/namecheap-registrar/extension.json'))->toBeTrue()
        ->and($manifest)->not->toBeNull()
        ->and($manifest->moduleDependencies)->toContain('domains');
});

it('maps Namecheap registration capabilities without claiming DNS or transfer support', function (): void {
    installAndEnableModules(['domains']);

    $api = Mockery::mock(NamecheapApi::class);
    $api->shouldReceive('check')->once()->with(['example.test'])->andReturn([
        'domains' => [[
            'domain' => 'example.test',
            'available' => true,
            'registration_price' => '12.50',
            'currency' => 'USD',
        ]],
    ]);
    $api->shouldReceive('register')->once()->with('example.test', 2)->andReturn([
        'domain' => 'example.test',
        'registered' => true,
        'domain_id' => '123',
        'order_id' => '456',
        'transaction_id' => '789',
        'charged_amount' => '25.00',
    ]);
    $api->shouldReceive('renew')->once()->with('example.test', 1)->andReturn([
        'domain' => 'example.test',
        'renewed' => true,
        'domain_id' => '123',
        'order_id' => '457',
        'transaction_id' => '790',
        'charged_amount' => '12.50',
    ]);
    $registrar = new NamecheapRegistrar($api);
    $registration = new DomainRegistration([
        'domain_name' => 'Example.test',
        'meta' => ['provider_settings' => ['years' => 2]],
    ]);

    expect($registrar->key())->toBe('namecheap-registrar')
        ->and($registrar->capabilities())->toBe(['availability_check', 'registration', 'renewal'])
        ->and($registrar->checkAvailability('Example.test'))->toMatchArray([
            'available' => true,
            'domain' => 'example.test',
            'price_minor' => 1250,
            'currency' => 'USD',
        ])
        ->and($registrar->register($registration))->toMatchArray([
            'provider_reference' => '789',
            'status' => 'active',
        ])
        ->and($registrar->renew($registration))->toMatchArray([
            'provider_reference' => '790',
            'status' => 'active',
        ]);
});
