<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

it('discovers all requested first-party server extensions', function (): void {
    $root = config('agovena.packages.optional_packages_path');
    expect($root)->toBeString()->not->toBe('');

    $expected = [
        'cpanel' => 'CPanel',
        'convoy' => 'Convoy',
        'directadmin' => 'DirectAdmin',
        'enhance' => 'Enhance',
        'plesk' => 'Plesk',
        'pterodactyl' => 'Pterodactyl',
        'virtfusion' => 'VirtFusion',
        'virtualizor' => 'Virtualizor',
    ];

    $discovered = app(ExtensionManager::class)->discover();
    $discoveredIds = array_map(static fn ($manifest): string => $manifest->id, $discovered);

    foreach ($expected as $id => $name) {
        $manifestPath = $root.'/extensions/provisioning/'.$id.'/extension.json';
        expect(File::exists($manifestPath))->toBeTrue("Missing manifest for {$id}");

        $manifest = json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        expect($manifest['id'] ?? null)->toBe($id)
            ->and($manifest['name'] ?? null)->toBe($name)
            ->and($manifest['category'] ?? null)->toBe('provisioning')
            ->and($manifest['module_dependencies'] ?? [])->toContain('provisioning')
            ->and($manifest['provider'] ?? null)->toContain('Agovena\\Extensions\\')
            ->and($discoveredIds)->toContain($id);
    }
});

it('loads each new server API through its first-party discovery namespace', function (): void {
    app(ModuleManager::class)->discover();
    app(ExtensionManager::class)->discover();

    $apis = [
        'Agovena\\Extensions\\CPanel\\HttpCPanelApi',
        'Agovena\\Extensions\\Convoy\\HttpConvoyApi',
        'Agovena\\Extensions\\DirectAdmin\\HttpDirectAdminApi',
        'Agovena\\Extensions\\Enhance\\HttpEnhanceApi',
        'Agovena\\Extensions\\Plesk\\HttpPleskApi',
        'Agovena\\Extensions\\Virtfusion\\HttpVirtfusionApi',
        'Agovena\\Extensions\\Virtualizor\\HttpVirtualizorApi',
    ];

    Http::fake(function (Request $request) {
        return Http::response(['ok' => true], 200);
    });

    foreach ($apis as $apiClass) {
        expect(class_exists($apiClass))->toBeTrue($apiClass);
        $api = new $apiClass(app(ExtensionSettingsRepository::class), [
            'api_url' => 'https://provider.invalid',
            'api_token' => '[REDACTED]',
            'api_username' => 'test-user',
            'api_secret' => '[REDACTED]',
            'verify_tls' => true,
            'timeout' => 2,
        ]);

        expect($api->connectionTest())->toBe(['ok' => true]);
    }
});
