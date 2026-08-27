<?php

declare(strict_types=1);

use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainRegistrarRegistry;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Shipping\ShippingCarrierRegistry;
use App\Models\AgovenaExtension;
use Illuminate\Validation\ValidationException;

/**
 * @return array<string, array{
 *     extension: string,
 *     module: string,
 *     registry: class-string,
 *     registryKey: string
 * }>
 */
function moduleBoundExtensions(): array
{
    return [
        'cloudflare-domain' => [
            'extension' => 'cloudflare-domain',
            'module' => 'domains',
            'registry' => DomainDnsProviderRegistry::class,
            'registryKey' => 'cloudflare-dns',
        ],
        'namecheap-domain' => [
            'extension' => 'namecheap-domain',
            'module' => 'domains',
            'registry' => DomainRegistrarRegistry::class,
            'registryKey' => 'namecheap-registrar',
        ],
        'cpanel' => [
            'extension' => 'cpanel',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'cpanel',
        ],
        'convoy' => [
            'extension' => 'convoy',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'convoy',
        ],
        'directadmin' => [
            'extension' => 'directadmin',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'directadmin',
        ],
        'enhance' => [
            'extension' => 'enhance',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'enhance',
        ],
        'plesk' => [
            'extension' => 'plesk',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'plesk',
        ],
        'pterodactyl' => [
            'extension' => 'pterodactyl',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'pterodactyl',
        ],
        'virtfusion' => [
            'extension' => 'virtfusion',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'virtfusion',
        ],
        'virtualizor' => [
            'extension' => 'virtualizor',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'virtualizor',
        ],
        'proxmox' => [
            'extension' => 'proxmox',
            'module' => 'provisioning',
            'registry' => ProvisionerRegistry::class,
            'registryKey' => 'proxmox',
        ],
        'postnl' => [
            'extension' => 'postnl',
            'module' => 'shipping',
            'registry' => ShippingCarrierRegistry::class,
            'registryKey' => 'postnl',
        ],
    ];
}

foreach (moduleBoundExtensions() as $label => $case) {
    test("{$label} install fails when parent module is not installed", function () use ($case): void {
        expect(app(ModuleManager::class)->isInstalled($case['module']))->toBeFalse();

        expect(fn () => app(ExtensionManager::class)->install($case['extension']))
            ->toThrow(
                ValidationException::class,
                "Install Module {$case['module']} before installing Extension {$case['extension']}.",
            );
    });

    test("{$label} can be installed when parent module is installed but disabled", function () use ($case): void {
        $modules = app(ModuleManager::class);
        $extensions = app(ExtensionManager::class);

        $modules->install($case['module']);

        $row = $extensions->install($case['extension']);

        expect($row)->toBeInstanceOf(AgovenaExtension::class)
            ->and($extensions->isInstalled($case['extension']))->toBeTrue()
            ->and($extensions->isEnabled($case['extension']))->toBeFalse()
            ->and($modules->isInstalled($case['module']))->toBeTrue()
            ->and($modules->isEnabled($case['module']))->toBeFalse();
    });

    test("{$label} enable fails when parent module is installed but disabled", function () use ($case): void {
        $modules = app(ModuleManager::class);
        $extensions = app(ExtensionManager::class);

        $modules->install($case['module']);
        $extensions->install($case['extension']);

        expect(fn () => $extensions->enable($case['extension']))
            ->toThrow(
                ValidationException::class,
                "Enable Module {$case['module']} before enabling Extension {$case['extension']}.",
            );
    });

    test("{$label} enables and registers runtime when parent module is enabled", function () use ($case): void {
        installAndEnableModule($case['module']);
        installAndEnableExtension($case['extension']);

        $registry = app($case['registry']);

        expect(app(ExtensionManager::class)->isEnabled($case['extension']))->toBeTrue()
            ->and($registry->get($case['registryKey']))->not->toBeNull();
    });

    test("{$label} is not booted after parent module is disabled and runtime rebuilds", function () use ($case): void {
        $modules = app(ModuleManager::class);
        $extensions = app(ExtensionManager::class);
        installAndEnableModule($case['module']);
        installAndEnableExtension($case['extension']);
        $registry = app($case['registry']);

        expect($registry->get($case['registryKey']))->not->toBeNull();

        $modules->disable($case['module']);
        $extensions->rebuildRuntime();

        expect($extensions->isEnabled($case['extension']))->toBeTrue()
            ->and($modules->isEnabled($case['module']))->toBeFalse()
            ->and($registry->get($case['registryKey']))->toBeNull();
    });

    test("{$label} bootEnabled skips manifest when parent module is disabled", function () use ($case): void {
        $modules = app(ModuleManager::class);
        $extensions = app(ExtensionManager::class);
        installAndEnableModule($case['module']);
        installAndEnableExtension($case['extension']);
        $modules->disable($case['module']);
        $registry = app($case['registry']);

        $registry->clear();
        $extensions->refresh();
        $extensions->bootEnabled();

        expect($extensions->isEnabled($case['extension']))->toBeTrue()
            ->and($registry->get($case['registryKey']))->toBeNull();
    });
}

test('all discovered extensions with module_dependencies are covered by dependency tests', function (): void {
    $covered = array_keys(moduleBoundExtensions());
    $withDeps = [];

    foreach (app(ExtensionManager::class)->discover() as $manifest) {
        if ($manifest->moduleDependencies === []) {
            continue;
        }

        $withDeps[] = $manifest->id;
        expect($manifest->moduleDependencies)->toHaveCount(1);
    }

    expect($withDeps)->toContain('pterodactyl', 'postnl')
        ->and(collect($withDeps)->sort()->values()->all())
        ->toEqual(collect($covered)->sort()->values()->all());
});
