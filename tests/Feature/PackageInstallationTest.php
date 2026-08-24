<?php

declare(strict_types=1);

use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\ComposerRunner;
use App\Agovena\Packages\PackageInstaller;
use App\Agovena\Packages\PackageSource;
use App\Agovena\Packages\PackageSourceValidator;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Livewire\Admin\Modules\Index as ModulesIndex;
use App\Models\AgovenaModule;
use App\Models\AgovenaPackage;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\Support\FakeComposerRunner;

uses(CreatesStaff::class);

afterEach(function (): void {
    File::deleteDirectory(storage_path('app/packages'));
});

function sampleModulePath(): string
{
    return base_path('tests/fixtures/packages/modules/sample');
}

function sampleExtensionPath(): string
{
    return base_path('tests/fixtures/packages/extensions/sample-gateway');
}

test('path-installed module uses the same lifecycle as bundled modules', function () {
    $installer = app(PackageInstaller::class);
    $package = $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));

    $modules = app(ModuleManager::class);

    expect($package->agovena_id)->toBe('sample')
        ->and($package->source_type)->toBe(PackageSourceType::Path)
        ->and($modules->isInstalled('sample'))->toBeTrue()
        ->and($modules->isEnabled('sample'))->toBeFalse()
        ->and(is_dir(storage_path('app/packages/modules/sample')))->toBeTrue();

    $modules->enable('sample');

    expect($modules->isEnabled('sample'))->toBeTrue()
        ->and(app(ProductCapabilityRegistry::class)->has('sample'))->toBeTrue();

    $modules->disable('sample');

    expect($modules->isEnabled('sample'))->toBeFalse()
        ->and(AgovenaModule::query()->where('module_id', 'sample')->exists())->toBeTrue();

    $installer->uninstall(PackageKind::Module, 'sample');

    expect($modules->isInstalled('sample'))->toBeFalse()
        ->and(is_dir(storage_path('app/packages/modules/sample')))->toBeTrue();

    $installer->purge(PackageKind::Module, 'sample');

    expect(is_dir(storage_path('app/packages/modules/sample')))->toBeFalse()
        ->and(AgovenaPackage::query()->where('agovena_id', 'sample')->exists())->toBeFalse();
});

test('composer source installs through the runner without a shell string', function () {
    $fake = new FakeComposerRunner;
    $fake->map('agovena-fixtures/sample-module', sampleModulePath());
    $this->app->instance(ComposerRunner::class, $fake);

    $package = app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Composer,
        locator: 'agovena-fixtures/sample-module',
        constraint: '^1.0',
        composerName: 'agovena-fixtures/sample-module',
    ));

    expect($package->composer_name)->toBe('agovena-fixtures/sample-module')
        ->and($fake->required)->toHaveCount(1)
        ->and($fake->required[0]['name'])->toBe('agovena-fixtures/sample-module')
        ->and($fake->required[0]['constraint'])->toBe('^1.0')
        ->and(app(ModuleManager::class)->manifest('sample'))->not->toBeNull();
});

test('git source requires an allowlisted https repository', function () {
    $fake = new FakeComposerRunner;
    $fake->map('vendor/sample-module', sampleModulePath());
    $this->app->instance(ComposerRunner::class, $fake);

    $package = app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Vcs,
        locator: 'https://github.com/vendor/sample-module',
        constraint: '*',
        composerName: 'vendor/sample-module',
    ));

    expect($package->source_type)->toBe(PackageSourceType::Vcs)
        ->and($fake->required[0]['url'])->toBe('https://github.com/vendor/sample-module');
});

test('package source validator rejects unsafe names urls and traversal', function () {
    $validator = app(PackageSourceValidator::class);

    expect(fn () => $validator->assert(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Composer,
        locator: 'vendor/pkg; rm -rf /',
        composerName: 'vendor/pkg; rm -rf /',
    )))->toThrow(ValidationException::class);

    expect(fn () => $validator->assert(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Vcs,
        locator: 'https://evil.example/vendor/pkg',
        composerName: 'vendor/pkg',
    )))->toThrow(ValidationException::class);

    expect(fn () => $validator->assert(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Vcs,
        locator: 'https://github.com/vendor/pkg?raw=1',
        composerName: 'vendor/pkg',
    )))->toThrow(ValidationException::class);

    expect(fn () => $validator->assert(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: base_path('modules/../.env'),
    )))->toThrow(ValidationException::class);

    expect(fn () => $validator->assertComposerName('not a package'))->toThrow(ValidationException::class);
});

test('legacy bundled package records cannot be updated or purged', function () {
    AgovenaPackage::query()->create([
        'kind' => PackageKind::Module,
        'agovena_id' => 'inventory',
        'source_type' => PackageSourceType::Bundled,
        'source_locator' => 'inventory',
        'version_constraint' => '*',
        'is_bundled' => true,
    ]);

    expect(fn () => app(PackageInstaller::class)->update(PackageKind::Module, 'inventory'))
        ->toThrow(ValidationException::class);

    expect(fn () => app(PackageInstaller::class)->purge(PackageKind::Module, 'inventory'))
        ->toThrow(ValidationException::class);
});

test('extension packages install enable and purge independently of modules', function () {
    $installer = app(PackageInstaller::class);
    $installer->install(new PackageSource(
        kind: PackageKind::Extension,
        sourceType: PackageSourceType::Path,
        locator: sampleExtensionPath(),
    ));

    $extensions = app(ExtensionManager::class);
    expect($extensions->manifest('sample-gateway'))->not->toBeNull();

    $extensions->enable('sample-gateway');
    expect($extensions->isEnabled('sample-gateway'))->toBeTrue();

    $installer->purge(PackageKind::Extension, 'sample-gateway');
    $extensions->refresh();
    expect($extensions->manifest('sample-gateway'))->toBeNull();
});

test('admin modules custom tab shows zip upload install form', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(ModulesIndex::class)
        ->set('tab', 'custom')
        ->assertOk()
        ->assertSee(__('admin.packages.zip_title'))
        ->assertSee(__('admin.packages.actions.choose_zip'))
        ->assertDontSee(__('admin.packages.install_title'));
});

test('malformed package manifests fail without breaking the application boot', function () {
    $dir = storage_path('app/packages/staging/broken-manifest');
    File::ensureDirectoryExists($dir);
    File::put($dir.DIRECTORY_SEPARATOR.'module.json', '{not-json');

    expect(fn () => app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: $dir,
    )))->toThrow(ValidationException::class);

    expect(app(ModuleManager::class)->isInstalled('broken-manifest'))->toBeFalse();
});

test('composer failure leaves no package registration behind', function () {
    $fake = new FakeComposerRunner;
    $this->app->instance(ComposerRunner::class, $fake);

    expect(fn () => app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Composer,
        locator: 'agovena-fixtures/missing-module',
        constraint: '^1.0',
        composerName: 'agovena-fixtures/missing-module',
    )))->toThrow(ValidationException::class);

    expect(AgovenaPackage::query()->where('agovena_id', 'sample')->exists())->toBeFalse()
        ->and(AgovenaPackage::query()->where('composer_name', 'agovena-fixtures/missing-module')->exists())->toBeFalse()
        ->and(app(ModuleManager::class)->isInstalled('sample'))->toBeFalse()
        ->and(is_dir(storage_path('app/packages/modules/sample')))->toBeFalse();
});

test('migration failure rolls back package registration and files', function () {
    $path = base_path('tests/fixtures/packages/modules/broken-migrate');

    expect(fn () => app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: $path,
    )))->toThrow(RuntimeException::class);

    expect(AgovenaPackage::query()->where('agovena_id', 'broken-migrate')->exists())->toBeFalse()
        ->and(app(ModuleManager::class)->isInstalled('broken-migrate'))->toBeFalse()
        ->and(is_dir(storage_path('app/packages/modules/broken-migrate')))->toBeFalse();
});

test('zip-installed module extracts nested package root and installs', function () {
    $zipPath = storage_path('app/packages/uploads/sample-module.zip');
    File::ensureDirectoryExists(dirname($zipPath));

    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $source = sampleModulePath();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        $relative = 'sample/'.str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
        if ($file->isDir()) {
            $zip->addEmptyDir(rtrim($relative, '/'));
        } else {
            $zip->addFile($file->getPathname(), $relative);
        }
    }
    $zip->close();

    $package = app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Zip,
        locator: $zipPath,
    ));

    expect($package->agovena_id)->toBe('sample')
        ->and($package->source_type)->toBe(PackageSourceType::Zip)
        ->and(app(ModuleManager::class)->isInstalled('sample'))->toBeTrue()
        ->and(is_dir(storage_path('app/packages/modules/sample')))->toBeTrue();
});
