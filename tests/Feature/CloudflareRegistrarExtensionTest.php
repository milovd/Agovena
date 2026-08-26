<?php

declare(strict_types=1);

use Agovena\Extensions\CloudflareRegistrar\CloudflareApi;
use Agovena\Extensions\CloudflareRegistrar\CloudflareRegistrar;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Extensions\ExtensionManager;
use Illuminate\Support\Facades\File;

it('discovers the Cloudflare Registrar extension with an explicit domains dependency', function (): void {
    $root = config('agovena.packages.optional_packages_path');
    $catalog = config('agovena.packages.monorepo.packages');
    $discovered = app(ExtensionManager::class)->discover();
    $manifest = collect($discovered)->firstWhere('id', 'cloudflare-registrar');

    expect($catalog['cloudflare-registrar'] ?? null)->toBe([
        'kind' => 'extension',
        'path' => 'extensions/domains/cloudflare-registrar',
    ])
        ->and(File::exists($root.'/extensions/domains/cloudflare-registrar/extension.json'))->toBeTrue()
        ->and($manifest)->not->toBeNull()
        ->and($manifest->moduleDependencies)->toContain('domains');
});

it('maps Cloudflare beta capabilities without claiming unsupported lifecycle operations', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('cloudflare-registrar');

    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('check')->once()->with(['acmecorp.dev'])->andReturn([
        'domains' => [[
            'name' => 'acmecorp.dev',
            'registrable' => true,
            'pricing' => [
                'registration_cost' => '10.11',
                'currency' => 'USD',
            ],
        ]],
    ]);
    $api->shouldReceive('register')->once()->with('acmecorp.dev', [
        'auto_renew' => true,
    ])->andReturn([
        'id' => 'registration-123',
        'domain_name' => 'acmecorp.dev',
        'status' => 'pending',
        'expires_at' => null,
    ]);
    $registrar = new CloudflareRegistrar($api);

    $availability = $registrar->checkAvailability('AcmeCorp.dev');
    $registration = new DomainRegistration([
        'domain_name' => 'acmecorp.dev',
        'auto_renew' => true,
    ]);
    $registered = $registrar->register($registration);

    expect($registrar->key())->toBe('cloudflare-registrar')
        ->and($registrar->capabilities())->toBe(['availability_check', 'registration'])
        ->and($availability)->toMatchArray([
            'available' => true,
            'domain' => 'acmecorp.dev',
            'price_minor' => 1011,
            'currency' => 'USD',
        ])
        ->and($registered)->toMatchArray([
            'provider_reference' => 'registration-123',
            'status' => 'pending',
        ]);
});
