<?php

declare(strict_types=1);

use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\OptionalPackagesPath;
use App\Agovena\Packages\PackageCatalog;
use App\Agovena\Packages\PackageCompatibility;
use App\Agovena\Packages\PackageInstaller;
use App\Agovena\Packages\PackageSource;
use App\Enums\PackageKind;
use App\Enums\PackageLifecycle;
use App\Enums\PackageSourceType;
use App\Livewire\Admin\Modules\Index as ModulesIndex;
use App\Models\AgovenaPackage;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/*
 * Compatibility rules for Modules and Extensions (see PackageCompatibility):
 * while Core is 0.x, a patch release must keep packages working and a minor
 * release may break them, and Core can require a minimum package version.
 */

afterEach(function (): void {
    File::deleteDirectory(storage_path('app/packages'));
    if (isset($this->lifecycleSource)) {
        File::deleteDirectory($this->lifecycleSource);
    }
});

/**
 * A writable copy of the sample Module fixture, so a test can publish a newer version of it.
 */
function lifecycleSampleModule(string $version = '1.0.0'): string
{
    $path = storage_path('framework/testing/lifecycle-'.bin2hex(random_bytes(4)).'/sample');
    File::copyDirectory(base_path('tests/fixtures/packages/modules/sample'), $path);
    setLifecycleSampleVersion($path, $version);
    test()->lifecycleSource = dirname($path);
    config(['agovena.packages.extra_path_prefixes' => [dirname($path)]]);

    return $path;
}

function setLifecycleSampleVersion(string $path, string $version): void
{
    $manifest = json_decode((string) File::get($path.'/module.json'), true);
    $manifest['version'] = $version;
    File::put($path.'/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function installLifecycleSample(string $path): AgovenaPackage
{
    return app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: $path,
    ));
}

test('the first-party constraint accepts the current Core and its patch releases but not the next minor release', function (string $platform, bool $accepted) {
    config(['agovena.version' => $platform]);

    expect(app(PackageCompatibility::class)->acceptsPlatform('>=0.0.1 <0.1.0'))->toBe($accepted);
})->with([
    'current release' => ['0.0.1', true],
    'next patch release' => ['0.0.2', true],
    'later patch release' => ['0.0.9', true],
    'next minor release' => ['0.1.0', false],
    'next major release' => ['1.0.0', false],
    'older release' => ['0.0.0', false],
]);

test('the old caret constraint would have broken every package on the next patch release', function () {
    config(['agovena.version' => '0.0.2']);

    expect(app(PackageCompatibility::class)->acceptsPlatform('^0.0.1'))->toBeFalse();
});

/**
 * @return list<array<string, mixed>>
 */
function firstPartyManifests(): array
{
    $root = OptionalPackagesPath::root();
    if ($root === null) {
        test()->markTestSkipped('optional-packages is not available.');
    }

    $files = [
        ...File::glob($root.'/modules/*/module.json'),
        ...File::glob($root.'/extensions/*/*/extension.json'),
    ];
    expect($files)->not->toBeEmpty();

    return array_map(fn (string $file): array => json_decode((string) File::get($file), true), $files);
}

test('every first-party package runs on the current Core', function () {
    $compatibility = app(PackageCompatibility::class);

    foreach (firstPartyManifests() as $manifest) {
        expect($compatibility->problem((string) $manifest['id'], (string) $manifest['version'], (string) $manifest['agovena']))
            ->toBeNull("{$manifest['id']} {$manifest['version']} must run on Agovena ".config('agovena.version'));
    }
});

test('every first-party package keeps running on the next Core patch release', function () {
    [$major, $minor, $patch] = array_map('intval', explode('.', (string) config('agovena.version')));
    $nextPatch = $major.'.'.$minor.'.'.($patch + 1);
    config(['agovena.version' => $nextPatch]);
    $compatibility = app(PackageCompatibility::class);

    $released = [];
    foreach (firstPartyManifests() as $manifest) {
        $constraint = (string) $manifest['agovena'];
        // Releases before the range convention declared ^0.0.1, which Composer reads as
        // >=0.0.1 <0.0.2. CI also tests Core against those released packages; the
        // coordinated-packages job checks the current package sources strictly.
        if (str_starts_with($constraint, '^0.0.')) {
            $released[] = $manifest['id'];

            continue;
        }

        expect($compatibility->acceptsPlatform($constraint))
            ->toBeTrue("{$manifest['id']} ({$constraint}) must accept Agovena {$nextPatch}");
    }

    if ($released !== []) {
        $this->markTestSkipped('Released packages still use a caret constraint: '.implode(', ', $released));
    }
});

test('a minimum package version blocks enabling an older install', function () {
    installLifecycleSample(lifecycleSampleModule('1.0.0'));
    config(['agovena.packages.minimum_versions' => ['sample' => '1.1.0']]);

    expect(fn () => app(ModuleManager::class)->enable('sample'))
        ->toThrow(ValidationException::class, 'requires version 1.1.0 or newer');

    expect(app(ModuleManager::class)->isEnabled('sample'))->toBeFalse();
});

test('a minimum package version blocks installing an older package', function () {
    config(['agovena.packages.minimum_versions' => ['sample' => '1.1.0']]);

    expect(fn () => installLifecycleSample(lifecycleSampleModule('1.0.0')))
        ->toThrow(ValidationException::class);

    expect(app(ModuleManager::class)->isInstalled('sample'))->toBeFalse();
});

test('a package that meets the minimum version installs and enables', function () {
    config(['agovena.packages.minimum_versions' => ['sample' => '1.1.0']]);
    installLifecycleSample(lifecycleSampleModule('1.1.0'));

    app(ModuleManager::class)->enable('sample');

    expect(app(ModuleManager::class)->isEnabled('sample'))->toBeTrue();
});

test('an enabled package that this Core cannot run stays enabled but is not booted', function () {
    installLifecycleSample(lifecycleSampleModule('1.0.0'));
    app(ModuleManager::class)->enable('sample');

    config(['agovena.packages.minimum_versions' => ['sample' => '1.1.0']]);
    app()->forgetInstance(ProductCapabilityRegistry::class);
    app()->forgetInstance(ModuleManager::class);
    app(ModuleManager::class)->bootEnabled();

    expect(app(ModuleManager::class)->isEnabled('sample'))->toBeTrue()
        ->and(app(ProductCapabilityRegistry::class)->has('sample'))->toBeFalse()
        ->and(app(ModuleManager::class)->status('sample')['compatible'])->toBeFalse();

    config(['agovena.packages.minimum_versions' => []]);
    app()->forgetInstance(ProductCapabilityRegistry::class);
    app()->forgetInstance(ModuleManager::class);
    app(ModuleManager::class)->bootEnabled();

    expect(app(ProductCapabilityRegistry::class)->has('sample'))->toBeTrue();
});

test('the catalog offers an update before reporting an incompatible install', function () {
    installLifecycleSample(lifecycleSampleModule('1.0.0'));
    config(['agovena.packages.minimum_versions' => ['sample' => '1.1.0']]);

    $row = fn (): array => collect(app(PackageCatalog::class)->modules())->firstWhere('manifest.id', 'sample');

    expect($row()['lifecycle'])->toBe(PackageLifecycle::Incompatible)
        ->and($row()['compatibility_error'])->toContain('1.1.0');

    AgovenaPackage::query()->where('agovena_id', 'sample')->update(['available_version' => '1.1.0']);
    app()->forgetInstance(PackageCatalog::class);

    expect($row()['lifecycle'])->toBe(PackageLifecycle::UpdateAvailable);
});

test('agovena:upgrade updates an installed package that has a newer version available', function () {
    $source = lifecycleSampleModule('1.0.0');
    installLifecycleSample($source);
    app(ModuleManager::class)->enable('sample');

    setLifecycleSampleVersion($source, '1.0.1');
    AgovenaPackage::query()->where('agovena_id', 'sample')->update(['available_version' => '1.0.1']);

    $this->artisan('agovena:upgrade')
        ->expectsOutputToContain('Updated module sample to 1.0.1.')
        ->assertSuccessful();

    expect(AgovenaPackage::query()->where('agovena_id', 'sample')->value('installed_version'))->toBe('1.0.1')
        ->and(app(ModuleManager::class)->isEnabled('sample'))->toBeTrue();
});

test('agovena:upgrade updates an enabled package that is below the minimum Core requires', function () {
    $source = lifecycleSampleModule('1.0.0');
    installLifecycleSample($source);
    app(ModuleManager::class)->enable('sample');

    setLifecycleSampleVersion($source, '1.1.0');
    config(['agovena.packages.minimum_versions' => ['sample' => '1.1.0']]);

    $this->artisan('agovena:upgrade')
        ->expectsOutputToContain('Updated module sample to 1.1.0.')
        ->assertSuccessful();

    expect(AgovenaPackage::query()->where('agovena_id', 'sample')->value('installed_version'))->toBe('1.1.0')
        ->and(app(ModuleManager::class)->isEnabled('sample'))->toBeTrue()
        ->and(app(ModuleManager::class)->status('sample')['compatible'])->toBeTrue();
});

test('agovena:upgrade fails and names enabled packages that still cannot run', function () {
    installLifecycleSample(lifecycleSampleModule('1.0.0'));
    app(ModuleManager::class)->enable('sample');
    config(['agovena.packages.minimum_versions' => ['sample' => '1.1.0']]);

    $this->artisan('agovena:upgrade')
        ->expectsOutputToContain('Could not update module sample')
        ->expectsOutputToContain('sample: Agovena')
        ->assertFailed();

    expect(app(ModuleManager::class)->isEnabled('sample'))->toBeTrue();
});

test('agovena:upgrade --without-package-updates leaves installed packages alone', function () {
    $source = lifecycleSampleModule('1.0.0');
    installLifecycleSample($source);

    setLifecycleSampleVersion($source, '1.0.1');
    AgovenaPackage::query()->where('agovena_id', 'sample')->update(['available_version' => '1.0.1']);

    $this->artisan('agovena:upgrade', ['--without-package-updates' => true])
        ->doesntExpectOutputToContain('Updated module sample')
        ->assertSuccessful();

    expect(AgovenaPackage::query()->where('agovena_id', 'sample')->value('installed_version'))->toBe('1.0.0');
});

test('new packages are scaffolded with the constraint for the current Core minor release', function (string $platform, string $expected) {
    config(['agovena.version' => $platform]);

    expect(app(PackageCompatibility::class)->recommendedConstraint())->toBe($expected);
})->with([
    ['0.0.1', '>=0.0.0 <0.1.0'],
    ['0.0.7', '>=0.0.0 <0.1.0'],
    ['0.3.2', '>=0.3.0 <0.4.0'],
    ['1.4.0', '>=1.4.0 <2.0.0'],
]);

test('the Modules screen offers and runs an update for an installed Module', function () {
    $source = lifecycleSampleModule('1.0.0');
    installLifecycleSample($source);
    app(ModuleManager::class)->enable('sample');
    setLifecycleSampleVersion($source, '1.0.1');
    AgovenaPackage::query()->where('agovena_id', 'sample')->update(['available_version' => '1.0.1']);

    $screen = Livewire::actingAs($this->createStaff())
        ->test(ModulesIndex::class)
        ->set('customModuleIds', ['sample'])
        ->assertSeeHtml("wire:click=\"updatePackage('sample')\"");

    $screen->call('updatePackage', 'sample');

    expect(AgovenaPackage::query()->where('agovena_id', 'sample')->value('installed_version'))->toBe('1.0.1')
        ->and(app(ModuleManager::class)->isEnabled('sample'))->toBeTrue();
});

test('the Modules screen hides the update action from staff who cannot manage Modules', function () {
    $source = lifecycleSampleModule('1.0.0');
    installLifecycleSample($source);
    app(ModuleManager::class)->enable('sample');
    AgovenaPackage::query()->where('agovena_id', 'sample')->update(['available_version' => '1.0.1']);

    Livewire::actingAs($this->createStaff(permissions: ['modules.view']))
        ->test(ModulesIndex::class)
        ->set('customModuleIds', ['sample'])
        ->assertDontSeeHtml("updatePackage('sample')")
        ->call('updatePackage', 'sample')
        ->assertForbidden();
});
