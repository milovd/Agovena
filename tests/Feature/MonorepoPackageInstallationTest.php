<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\MonorepoCheckout;
use App\Agovena\Packages\MonorepoPackageMap;
use App\Agovena\Packages\PackageInstaller;
use App\Agovena\Packages\PackageSource;
use App\Agovena\Packages\PackageSourceValidator;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Models\AgovenaPackage;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeMonorepoCheckout;

uses()->group('packages');

afterEach(function (): void {
    File::deleteDirectory(storage_path('app/packages'));
});

function monorepoFixtureRoot(): string
{
    return base_path('tests/fixtures/packages/monorepo');
}

function configureMonorepoFixtures(): void
{
    config([
        'agovena.packages.monorepo.repository' => 'https://github.com/agovena/packages-fixture',
        'agovena.packages.monorepo.packages' => [
            'sample' => ['kind' => 'module', 'path' => 'modules/sample'],
            'sample-gateway' => ['kind' => 'extension', 'path' => 'extensions/sample-gateway'],
        ],
    ]);
}

function bindFakeMonorepoCheckout(): FakeMonorepoCheckout
{
    $fake = new FakeMonorepoCheckout(app(MonorepoPackageMap::class));
    $fake->map('https://github.com/agovena/packages-fixture', monorepoFixtureRoot());
    app()->forgetInstance(MonorepoCheckout::class);
    app()->instance(MonorepoCheckout::class, $fake);

    return $fake;
}

test('monorepo source installs a module subdirectory into storage', function () {
    configureMonorepoFixtures();
    $fake = bindFakeMonorepoCheckout();

    $package = app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Monorepo,
        locator: 'https://github.com/agovena/packages-fixture',
        constraint: 'main',
        composerName: 'sample',
    ));

    expect($package->agovena_id)->toBe('sample')
        ->and($package->source_type)->toBe(PackageSourceType::Monorepo)
        ->and($package->composer_name)->toBe('sample')
        ->and($fake->resolved)->toHaveCount(1)
        ->and($fake->resolved[0]['subdirectory'])->toBe('modules/sample')
        ->and(is_dir(storage_path('app/packages/modules/sample')))->toBeTrue()
        ->and(app(ModuleManager::class)->isInstalled('sample'))->toBeTrue();
});

test('monorepo install validates manifest id and kind against mapping', function () {
    configureMonorepoFixtures();
    bindFakeMonorepoCheckout();

    expect(fn () => app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Extension,
        sourceType: PackageSourceType::Monorepo,
        locator: 'https://github.com/agovena/packages-fixture',
        constraint: 'main',
        composerName: 'sample',
    )))->toThrow(ValidationException::class);
});

test('monorepo extension installs enable and purge through full lifecycle', function () {
    configureMonorepoFixtures();
    bindFakeMonorepoCheckout();

    $installer = app(PackageInstaller::class);
    $installer->install(new PackageSource(
        kind: PackageKind::Extension,
        sourceType: PackageSourceType::Monorepo,
        locator: 'https://github.com/agovena/packages-fixture',
        constraint: 'v1.0.0',
        composerName: 'sample-gateway',
    ));

    $extensions = app(ExtensionManager::class);
    expect($extensions->manifest('sample-gateway'))->not->toBeNull();

    $extensions->enable('sample-gateway');
    expect($extensions->isEnabled('sample-gateway'))->toBeTrue();

    $installer->uninstall(PackageKind::Extension, 'sample-gateway');
    expect($extensions->isInstalled('sample-gateway'))->toBeFalse()
        ->and(is_dir(storage_path('app/packages/extensions/sample-gateway')))->toBeTrue();

    $installer->purge(PackageKind::Extension, 'sample-gateway');
    $extensions->refresh();

    expect(is_dir(storage_path('app/packages/extensions/sample-gateway')))->toBeFalse()
        ->and(AgovenaPackage::query()->where('agovena_id', 'sample-gateway')->exists())->toBeFalse()
        ->and($extensions->manifest('sample-gateway'))->toBeNull();
});

test('monorepo update re-checkouts mapped subdirectory', function () {
    configureMonorepoFixtures();
    $fake = bindFakeMonorepoCheckout();

    $installer = app(PackageInstaller::class);
    $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Monorepo,
        locator: 'https://github.com/agovena/packages-fixture',
        constraint: 'main',
        composerName: 'sample',
    ));

    $fake->resolved = [];
    $installer->update(PackageKind::Module, 'sample');

    expect($fake->resolved)->toHaveCount(1)
        ->and($fake->resolved[0]['ref'])->toBe('main');
});

test('monorepo validator rejects unknown keys unsafe refs and traversal', function () {
    configureMonorepoFixtures();
    $validator = app(PackageSourceValidator::class);

    expect(fn () => $validator->assert(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Monorepo,
        locator: '',
        constraint: 'main',
        composerName: 'missing-package',
    )))->toThrow(ValidationException::class);

    expect(fn () => $validator->assert(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Monorepo,
        locator: 'https://github.com/agovena/packages-fixture',
        constraint: 'main; rm -rf /',
        composerName: 'sample',
    )))->toThrow(ValidationException::class);

    expect(fn () => $validator->assert(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Monorepo,
        locator: 'https://github.com/agovena/packages-fixture',
        constraint: 'main',
        composerName: 'sample',
        subdirectory: '../secret',
    )))->toThrow(ValidationException::class);
});

test('monorepo defaults repository url from config when locator is empty', function () {
    configureMonorepoFixtures();
    bindFakeMonorepoCheckout();

    $package = app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Monorepo,
        locator: '',
        constraint: '*',
        composerName: 'sample',
    ));

    expect($package->source_locator)->toBe('https://github.com/agovena/packages-fixture');
});
