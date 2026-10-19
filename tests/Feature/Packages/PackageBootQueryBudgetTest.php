<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminNavigation;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Modules\ModuleManager;
use App\Models\AgovenaExtension;
use App\Models\AgovenaModule;
use Illuminate\Support\Facades\DB;

/*
 * Packages boot on every request. Reading the enabled state once per boot pass keeps that
 * cost flat; one table check plus one enabled query per discovered package added dozens of
 * queries to every page (information_schema lookups on MySQL).
 */
function packageStateQueries(Closure $boot): int
{
    $count = 0;
    DB::listen(function ($query) use (&$count): void {
        if (preg_match('/sqlite_master|information_schema|"agovena_extensions"|"agovena_modules"/', $query->sql) === 1) {
            $count++;
        }
    });

    $boot();

    return $count;
}

test('booting extensions reads the enabled state once instead of once per discovered extension', function (): void {
    $extensions = app(ExtensionManager::class);
    expect(count($extensions->discover()))->toBeGreaterThan(3);

    expect(packageStateQueries(fn () => $extensions->bootEnabled()))->toBeLessThanOrEqual(2);
});

test('booting modules reads the enabled state once instead of once per discovered module', function (): void {
    $modules = app(ModuleManager::class);
    expect(count($modules->discover()))->toBeGreaterThan(1);

    expect(packageStateQueries(fn () => $modules->bootEnabled()))->toBeLessThanOrEqual(2);
});

test('the one-pass enabled state still boots enabled extensions and skips disabled ones', function (): void {
    $extensions = app(ExtensionManager::class);
    [$enabled, $disabled] = collect($extensions->discover())
        ->filter(fn ($manifest) => $manifest->productionReady && $manifest->dependencies === [] && $manifest->moduleDependencies === [])
        ->values()
        ->all();

    foreach ([$enabled, $disabled] as $manifest) {
        AgovenaExtension::query()->create([
            'extension_id' => $manifest->id,
            'version' => $manifest->version,
            'enabled' => $manifest === $enabled,
            'installed_at' => now(),
        ]);
    }

    $extensions->bootEnabled();

    expect(app()->providerIsLoaded($enabled->provider))->toBeTrue()
        ->and(app()->providerIsLoaded($disabled->provider))->toBeFalse();
});

test('the pending migration check reads package state once per manager', function (): void {
    $status = app(ApplicationSchemaStatus::class);

    expect(packageStateQueries(fn () => $status->pending()))->toBeLessThanOrEqual(5);
});

test('admin navigation checks enabled modules once for all module-owned items', function (): void {
    $modules = app(ModuleManager::class);
    $ids = array_map(fn ($manifest) => $manifest->id, $modules->discover());
    expect(count($ids))->toBeGreaterThan(2);
    AgovenaModule::query()->create(['module_id' => $ids[0], 'version' => '1.0.0', 'enabled' => true, 'installed_at' => now()]);

    $items = collect($ids)->map(fn (string $id, int $index) => new NavigationItem(
        id: 'qa-'.$id,
        label: $id,
        group: 'system',
        href: 'https://example.test/'.$index,
        moduleId: $id,
    ));

    $visible = null;
    $queries = packageStateQueries(function () use (&$visible, $items, $modules): void {
        $visible = AdminNavigation::filterVisible($items, $modules);
    });

    expect($queries)->toBeLessThanOrEqual(2)
        ->and($visible->pluck('moduleId')->all())->toBe([$ids[0]]);
});

test('enabled ids list exactly the enabled packages', function (): void {
    $modules = app(ModuleManager::class);
    $extensions = app(ExtensionManager::class);
    [$moduleOn, $moduleOff] = array_map(fn ($manifest) => $manifest->id, array_slice($modules->discover(), 0, 2));
    [$extensionOn, $extensionOff] = array_map(fn ($manifest) => $manifest->id, array_slice($extensions->discover(), 0, 2));

    AgovenaModule::query()->create(['module_id' => $moduleOn, 'version' => '1.0.0', 'enabled' => true, 'installed_at' => now()]);
    AgovenaModule::query()->create(['module_id' => $moduleOff, 'version' => '1.0.0', 'enabled' => false, 'installed_at' => now()]);
    AgovenaExtension::query()->create(['extension_id' => $extensionOn, 'version' => '1.0.0', 'enabled' => true, 'installed_at' => now()]);
    AgovenaExtension::query()->create(['extension_id' => $extensionOff, 'version' => '1.0.0', 'enabled' => false, 'installed_at' => now()]);

    expect($modules->enabledIds())->toBe([$moduleOn])
        ->and($extensions->enabledIds())->toBe([$extensionOn])
        ->and($modules->isEnabled($moduleOn))->toBeTrue()
        ->and($modules->isEnabled($moduleOff))->toBeFalse();
});
