<?php

declare(strict_types=1);

use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\ComposerRunner;
use App\Agovena\Packages\PackageInstaller;
use App\Agovena\Packages\PackageMigrationRunner;
use App\Agovena\Packages\PackageSource;
use App\Agovena\Packages\PackageSourceValidator;
use App\Agovena\Packages\ProcessComposerRunner;
use App\Agovena\Packages\ZipPackageExtractor;
use App\Enums\PackageKind;
use App\Enums\PackageSourceType;
use App\Livewire\Admin\Modules\Index as ModulesIndex;
use App\Models\AgovenaModule;
use App\Models\AgovenaPackage;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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

test('package purge refuses symlinked install paths', function () {
    $outside = storage_path('app/packages/outside-package');
    $link = storage_path('app/packages/modules/symlinked-package');
    File::ensureDirectoryExists($outside);
    File::ensureDirectoryExists(dirname($link));
    File::put($outside.'/marker.txt', 'outside');
    if (! @symlink($outside, $link)) {
        File::deleteDirectory($outside);
        $this->markTestSkipped('Symlink creation is unavailable in this Windows test environment.');
    }

    AgovenaPackage::query()->create([
        'kind' => PackageKind::Module,
        'agovena_id' => 'symlinked-package',
        'source_type' => PackageSourceType::Path,
        'source_locator' => $link,
        'install_path' => $link,
        'is_bundled' => false,
    ]);

    try {
        expect(fn () => app(PackageInstaller::class)->purge(PackageKind::Module, 'symlinked-package'))
            ->toThrow(RuntimeException::class);
        expect(File::exists($outside.'/marker.txt'))->toBeTrue();
    } finally {
        AgovenaPackage::query()->where('agovena_id', 'symlinked-package')->delete();
        if (is_link($link)) {
            @unlink($link);
        }
        File::deleteDirectory($outside);
    }
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

test('composer failure restores the composer project state', function () {
    $fake = new FakeComposerRunner;
    $fake->map('agovena-fixtures/broken-module', base_path('tests/fixtures/packages/modules/broken-migrate'));
    $composerRoot = storage_path('app/packages/composer');
    File::ensureDirectoryExists($composerRoot);
    File::put($composerRoot.'/composer.json', '{"require":{"existing/package":"1.0.0"}}');
    File::put($composerRoot.'/composer.lock', '{"content-hash":"original"}');
    $fake->onRequire = function () use ($composerRoot): void {
        File::put($composerRoot.'/composer.json', '{"require":{"new/package":"2.0.0"}}');
        File::put($composerRoot.'/composer.lock', '{"content-hash":"mutated"}');
    };
    $this->app->instance(ComposerRunner::class, $fake);

    expect(fn () => app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Composer,
        locator: 'agovena-fixtures/broken-module',
        constraint: '^1.0',
        composerName: 'agovena-fixtures/broken-module',
    )))->toThrow(RuntimeException::class);

    expect(File::get($composerRoot.'/composer.json'))->toBe('{"require":{"existing/package":"1.0.0"}}')
        ->and(File::get($composerRoot.'/composer.lock'))->toBe('{"content-hash":"original"}')
        ->and(File::exists(storage_path('app/packages/.composer-operation.json')))->toBeFalse()
        ->and(glob(storage_path('app/packages/.composer.*.snapshot')) ?: [])->toBe([]);
});
test('recovers an interrupted Composer operation before the next installation', function () {
    $fake = new FakeComposerRunner;
    $fake->map('agovena-fixtures/sample-module', sampleModulePath());
    $this->app->instance(ComposerRunner::class, $fake);

    $packagesRoot = storage_path('app/packages');
    $composerRoot = $packagesRoot.'/composer';
    $snapshot = $packagesRoot.'/.composer.interrupted.snapshot';
    $marker = $packagesRoot.'/.composer-operation.json';
    File::ensureDirectoryExists($composerRoot);
    File::put($composerRoot.'/composer.json', '{"require":{"existing/package":"1.0.0"}}');
    File::copyDirectory($composerRoot, $snapshot);
    File::put($composerRoot.'/composer.json', '{"require":{"corrupted/package":"9.9.9"}}');
    File::put($marker, json_encode([
        'root' => $composerRoot,
        'snapshot' => $snapshot,
        'existed' => true,
        'status' => 'ready',
    ], JSON_THROW_ON_ERROR));

    app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Composer,
        locator: 'agovena-fixtures/sample-module',
        constraint: '^1.0',
        composerName: 'agovena-fixtures/sample-module',
    ));

    expect(File::get($composerRoot.'/composer.json'))->toBe('{"require":{"existing/package":"1.0.0"}}')
        ->and(File::exists($marker))->toBeFalse()
        ->and(File::exists($snapshot))->toBeFalse();
});

test('failed Composer purge restores package rows lifecycle and files', function () {
    $fake = new FakeComposerRunner;
    $fake->map('agovena-fixtures/sample-module', sampleModulePath());
    $this->app->instance(ComposerRunner::class, $fake);
    $installer = app(PackageInstaller::class);
    $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Composer,
        locator: 'agovena-fixtures/sample-module',
        constraint: '^1.0',
        composerName: 'agovena-fixtures/sample-module',
    ));
    $fake->onRemove = static function (): void {
        throw new RuntimeException('remove failed');
    };

    expect(fn () => $installer->purge(PackageKind::Module, 'sample'))
        ->toThrow(RuntimeException::class, 'remove failed');

    expect(AgovenaPackage::query()->where('agovena_id', 'sample')->exists())->toBeTrue()
        ->and(AgovenaModule::query()->where('module_id', 'sample')->exists())->toBeTrue()
        ->and(is_dir(storage_path('app/packages/modules/sample')))->toBeTrue()
        ->and(File::exists(storage_path('app/packages/.composer-operation.json')))->toBeFalse()
        ->and(glob(storage_path('app/packages/.composer.*.snapshot')) ?: [])->toBe([]);
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

test('partial package migrations are compensated when a later migration fails', function () {
    $path = base_path('tests/fixtures/packages/modules/partial-migrate');

    expect(fn () => app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: $path,
    )))->toThrow(RuntimeException::class, 'Intentional partial package migration failure.');

    expect(Schema::hasTable('partial_migrate_records'))->toBeFalse()
        ->and(AgovenaPackage::query()->where('agovena_id', 'partial-migrate')->exists())->toBeFalse()
        ->and(is_dir(storage_path('app/packages/modules/partial-migrate')))->toBeFalse();
});

test('running package migration journals recover an interrupted migration batch', function () {
    $runner = app(PackageMigrationRunner::class);
    $root = base_path('tests/fixtures/packages/modules/partial-migrate');
    $journal = $runner->prepare('partial-migrate', $root);

    expect($journal)->toBeString()
        ->and(fn () => $runner->run('partial-migrate', $root, $journal))
        ->toThrow(RuntimeException::class, 'Intentional partial package migration failure.')
        ->and(Schema::hasTable('partial_migrate_records'))->toBeTrue();

    $runner->reconcile();

    expect(Schema::hasTable('partial_migrate_records'))->toBeFalse()
        ->and(glob(storage_path('app/packages/.migration-operations/migration-*.json')) ?: [])->toBe([]);
});

test('package migration rollback fails closed and retains its journal when the source disappears', function () {
    $runner = app(PackageMigrationRunner::class);
    $root = storage_path('app/packages/test-migration-missing-source');
    File::deleteDirectory($root);
    File::ensureDirectoryExists($root.'/database/migrations');
    File::put($root.'/database/migrations/2026_08_29_000001_placeholder.php', '<?php');

    $journal = $runner->prepare('test-migration-missing-source', $root);
    File::deleteDirectory($root.'/database/migrations');

    expect(fn () => $runner->rollback($journal))
        ->toThrow(RuntimeException::class, 'source')
        ->and(File::exists($journal))->toBeTrue();

    File::delete($journal);
    File::deleteDirectory($root);
});

test('package migration rollback fails closed and retains its journal for an empty source', function () {
    $runner = app(PackageMigrationRunner::class);
    $root = storage_path('app/packages/test-migration-empty-source');
    File::deleteDirectory($root);
    File::ensureDirectoryExists($root.'/database/migrations');

    $journal = $runner->prepare('test-migration-empty-source', $root);

    expect(fn () => $runner->rollback($journal))
        ->toThrow(RuntimeException::class, 'empty')
        ->and(File::exists($journal))->toBeTrue();

    File::delete($journal);
    File::deleteDirectory($root);
});

test('failed package updates restore the previous files and lifecycle rows', function () {
    $sourcePath = base_path('tests/fixtures/packages/modules/broken-migrate');
    $destination = storage_path('app/packages/modules/broken-migrate');
    File::ensureDirectoryExists($destination);
    File::put($destination.'/old-state.txt', 'previous package state');

    AgovenaPackage::query()->create([
        'kind' => PackageKind::Module,
        'agovena_id' => 'broken-migrate',
        'composer_name' => null,
        'source_type' => PackageSourceType::Path,
        'source_locator' => $sourcePath,
        'version_constraint' => '^0.9',
        'installed_version' => '0.9.0',
        'available_version' => '0.9.0',
        'install_path' => $destination,
        'is_bundled' => false,
    ]);
    AgovenaModule::query()->create([
        'module_id' => 'broken-migrate',
        'version' => '0.9.0',
        'enabled' => true,
        'installed_at' => now()->subDay(),
        'enabled_at' => now()->subDay(),
        'disabled_at' => null,
        'meta' => ['previous' => true],
    ]);

    expect(fn () => app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: $sourcePath,
    )))->toThrow(RuntimeException::class);

    $restoredPackage = AgovenaPackage::query()->where('agovena_id', 'broken-migrate')->firstOrFail();
    $restoredModule = AgovenaModule::query()->where('module_id', 'broken-migrate')->firstOrFail();

    expect(File::get($destination.'/old-state.txt'))->toBe('previous package state')
        ->and($restoredPackage->installed_version)->toBe('0.9.0')
        ->and($restoredPackage->version_constraint)->toBe('^0.9')
        ->and($restoredModule->version)->toBe('0.9.0')
        ->and($restoredModule->enabled)->toBeTrue()
        ->and($restoredModule->meta)->toBe(['previous' => true])
        ->and(File::exists(storage_path('app/packages/modules/.broken-migrate.staging')))->toBeFalse()
        ->and(File::exists(storage_path('app/packages/modules/.broken-migrate.backup')))->toBeFalse();
});

test('package updates reject an existing non-canonical install path', function () {
    $sourcePath = base_path('tests/fixtures/packages/modules/broken-migrate');
    $legacyPath = storage_path('app/packages/modules/broken-migrate-legacy');
    $canonicalPath = storage_path('app/packages/modules/broken-migrate');
    File::ensureDirectoryExists($legacyPath);
    File::put($legacyPath.'/legacy-state.txt', 'must remain untouched');

    $package = AgovenaPackage::query()->create([
        'kind' => PackageKind::Module,
        'agovena_id' => 'broken-migrate',
        'source_type' => PackageSourceType::Path,
        'source_locator' => $sourcePath,
        'install_path' => $legacyPath,
        'is_bundled' => false,
    ]);

    expect(fn () => app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: $sourcePath,
    )))->toThrow(RuntimeException::class, 'Existing package install path is not canonical.');

    expect($package->fresh()->install_path)->toBe($legacyPath)
        ->and(File::get($legacyPath.'/legacy-state.txt'))->toBe('must remain untouched')
        ->and(File::exists($canonicalPath))->toBeFalse();
});

test('failed package rollback reports the failed compensation phase', function () {
    $installer = app(PackageInstaller::class);
    $method = (new ReflectionClass($installer))->getMethod('rollbackFailedInstall');
    $method->setAccessible(true);

    $failure = $method->invoke(
        $installer,
        null,
        PackageKind::Module,
        'rollback-phase',
        [
            'id' => 999999,
            'kind' => PackageKind::Module->value,
            'agovena_id' => 'rollback-phase',
            'invalid_rollback_column' => 'must fail',
        ],
        null,
        null,
        new RuntimeException('primary failure'),
    );

    expect($failure)->toBeInstanceOf(RuntimeException::class)
        ->and($failure->getMessage())->toContain('database rollback');
});

test('rollback verification rejects a package row that should be absent', function () {
    $installer = app(PackageInstaller::class);
    AgovenaPackage::query()->create([
        'kind' => PackageKind::Module,
        'agovena_id' => 'rollback-verification',
        'source_type' => PackageSourceType::Path,
        'source_locator' => sampleModulePath(),
        'install_path' => storage_path('app/packages/modules/rollback-verification'),
        'is_bundled' => false,
    ]);

    $method = (new ReflectionClass($installer))->getMethod('verifyRestoredState');
    $method->setAccessible(true);

    expect(fn () => $method->invoke(
        $installer,
        PackageKind::Module,
        'rollback-verification',
        null,
        null,
        null,
    ))->toThrow(RuntimeException::class, 'rollback verification');
});

test('package updates reject a changed manifest ID', function () {
    $sourcePath = storage_path('app/packages/staging/wrong-id');
    $destination = storage_path('app/packages/modules/sample');
    File::copyDirectory(sampleModulePath(), $sourcePath);
    $manifest = json_decode(File::get($sourcePath.'/module.json'), true, flags: JSON_THROW_ON_ERROR);
    $manifest['id'] = 'different-package';
    File::put($sourcePath.'/module.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    File::ensureDirectoryExists($destination);
    File::put($destination.'/marker.txt', 'previous');
    AgovenaPackage::query()->create([
        'kind' => PackageKind::Module,
        'agovena_id' => 'sample',
        'source_type' => PackageSourceType::Path,
        'source_locator' => $sourcePath,
        'version_constraint' => '^1.0',
        'installed_version' => '1.0.0',
        'available_version' => '1.0.0',
        'install_path' => $destination,
        'is_bundled' => false,
    ]);

    expect(fn () => app(PackageInstaller::class)->update(PackageKind::Module, 'sample'))
        ->toThrow(ValidationException::class, __('admin.packages.package_id_mismatch', ['actual' => 'different-package', 'expected' => 'sample']));
    expect(File::get($destination.'/marker.txt'))->toBe('previous')
        ->and(AgovenaPackage::query()->where('agovena_id', 'sample')->exists())->toBeTrue()
        ->and(AgovenaPackage::query()->where('agovena_id', 'different-package')->exists())->toBeFalse();
});

test('successful package updates preserve an enabled module state', function () {
    $installer = app(PackageInstaller::class);
    $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));
    app(ModuleManager::class)->enable('sample');

    $updated = $installer->update(PackageKind::Module, 'sample');

    expect($updated->installed_version)->toBe('1.0.0')
        ->and(app(ModuleManager::class)->isEnabled('sample'))->toBeTrue()
        ->and(AgovenaModule::query()->where('module_id', 'sample')->value('enabled'))->toBeTrue();
});

test('interrupted package updates recover a valid destination and clear stale backups', function () {
    $installer = app(PackageInstaller::class);
    $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));
    $destination = storage_path('app/packages/modules/sample');
    $backup = storage_path('app/packages/modules/.sample.backup');
    expect(File::copyDirectory($destination, $backup))->toBeTrue();

    $updated = $installer->update(PackageKind::Module, 'sample');

    expect($updated->agovena_id)->toBe('sample')
        ->and(File::exists($backup))->toBeFalse()
        ->and(File::exists(storage_path('app/packages/modules/.sample.staging')))->toBeFalse();
});

test('package updates recover pending state before reading the update source', function () {
    $installer = app(PackageInstaller::class);
    $package = $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));
    $fingerprintMethod = new ReflectionMethod(PackageInstaller::class, 'packageTreeFingerprint');
    $fingerprintMethod->setAccessible(true);
    $prepareMethod = new ReflectionMethod(PackageInstaller::class, 'preparePackageOperationJournal');
    $prepareMethod->setAccessible(true);
    $updateMethod = new ReflectionMethod(PackageInstaller::class, 'updatePackageOperationJournal');
    $updateMethod->setAccessible(true);
    $journal = $prepareMethod->invoke($installer, 'install', PackageKind::Module, 'sample');
    $updateMethod->invoke($installer, $journal, [
        'destination' => $package->install_path,
        'previous_fingerprint' => $fingerprintMethod->invoke($installer, $package->install_path),
        'previous_state' => Crypt::encryptString(json_encode([
            'package' => $package->getAttributes(),
            'lifecycle' => null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ]);
    DB::table('agovena_packages')->where('id', $package->id)->update([
        'source_locator' => storage_path('app/packages/not-the-package'),
    ]);

    $updated = $installer->update(PackageKind::Module, 'sample');

    expect($updated->source_locator)->toBe(sampleModulePath())
        ->and(File::exists($journal))->toBeFalse();
});

test('zip extraction rejects traversal entries before writing outside the upload root', function () {
    $zipPath = storage_path('app/packages/uploads/unsafe-traversal.zip');
    File::ensureDirectoryExists(dirname($zipPath));
    $outside = storage_path('app/packages/escaped.txt');
    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue()
        ->and($zip->addFromString('../escaped.txt', 'must not escape'))->toBeTrue();
    $zip->close();

    expect(fn () => app(ZipPackageExtractor::class)->extract($zipPath, PackageKind::Module))
        ->toThrow(ValidationException::class, __('admin.packages.zip_unsafe_entry'));
    expect(File::exists($outside))->toBeFalse();
});

test('zip extraction rejects symlink entries from archive metadata', function () {
    $zipPath = storage_path('app/packages/uploads/unsafe-symlink.zip');
    File::ensureDirectoryExists(dirname($zipPath));
    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue()
        ->and($zip->addFromString('sample/link', 'must not link'))->toBeTrue();
    expect($zip->setExternalAttributesName(
        'sample/link',
        ZipArchive::OPSYS_UNIX,
        (0120000 | 0777) << 16,
    ))->toBeTrue();
    $zip->close();

    expect(fn () => app(ZipPackageExtractor::class)->extract($zipPath, PackageKind::Module))
        ->toThrow(ValidationException::class, __('admin.packages.zip_unsafe_entry'));
});

test('zip extraction rejects archives over configured resource limits', function () {
    config(['agovena.packages.zip_max_uncompressed_bytes' => 4]);
    $zipPath = storage_path('app/packages/uploads/oversized.zip');
    File::ensureDirectoryExists(dirname($zipPath));
    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue()
        ->and($zip->addFromString('sample/module.json', str_repeat('x', 5)))->toBeTrue();
    $zip->close();

    expect(fn () => app(ZipPackageExtractor::class)->extract($zipPath, PackageKind::Module))
        ->toThrow(ValidationException::class, __('admin.packages.zip_too_large'));
});

test('Composer diagnostics redact credentials before reaching validation errors', function () {
    $method = new ReflectionMethod(ProcessComposerRunner::class, 'scrubDiagnostics');
    $scrubbed = $method->invoke(new ProcessComposerRunner, 'https://user:'.'password@example.test/repo?'.'token='.'secret-token&ref=main password='.'another-secret '.'token:'.'third-secret');

    expect($scrubbed)->toContain('[REDACTED]')
        ->and($scrubbed)->not->toContain('password@example')
        ->and($scrubbed)->not->toContain('secret-token')
        ->and($scrubbed)->not->toContain('another-secret')
        ->and($scrubbed)->not->toContain('third-secret');
});

test('Composer remove fails closed on malformed managed state', function () {
    $composerRoot = storage_path('app/packages/composer');
    File::ensureDirectoryExists($composerRoot);
    $composerFile = $composerRoot.DIRECTORY_SEPARATOR.'composer.json';
    File::put($composerFile, '{');

    expect(fn () => (new ProcessComposerRunner)->remove('vendor/sample'))
        ->toThrow(RuntimeException::class);

    expect(File::get($composerFile))->toBe('{');
});

test('package purge rejects a sibling install path', function () {
    $sibling = storage_path('app/packages/modules/sibling');
    File::copyDirectory(sampleModulePath(), $sibling);
    AgovenaPackage::query()->create([
        'kind' => PackageKind::Module,
        'agovena_id' => 'sample',
        'source_type' => PackageSourceType::Path,
        'source_locator' => sampleModulePath(),
        'version_constraint' => '*',
        'installed_version' => '1.0.0',
        'available_version' => '1.0.0',
        'install_path' => $sibling,
        'is_bundled' => false,
    ]);

    expect(fn () => app(PackageInstaller::class)->purge(PackageKind::Module, 'sample'))
        ->toThrow(RuntimeException::class);

    expect(is_dir($sibling))->toBeTrue()
        ->and(glob(storage_path('app/packages/.purge-operations/purge-*.json')) ?: [])->toBe([])
        ->and(glob(storage_path('app/packages/.package-operations/package-*.json')) ?: [])->toBe([]);
});

test('package purge supports a package without a materialized directory', function () {
    File::ensureDirectoryExists(storage_path('app/packages/modules'));
    AgovenaPackage::query()->create([
        'kind' => PackageKind::Module,
        'agovena_id' => 'sample',
        'source_type' => PackageSourceType::Path,
        'source_locator' => sampleModulePath(),
        'version_constraint' => '*',
        'installed_version' => '1.0.0',
        'available_version' => '1.0.0',
        'install_path' => storage_path('app/packages/modules/missing'),
        'is_bundled' => false,
    ]);

    app(PackageInstaller::class)->purge(PackageKind::Module, 'sample');

    expect(AgovenaPackage::query()->where('agovena_id', 'sample')->exists())->toBeFalse();
});

test('Composer diagnostics redact token password bearer and URL credentials', function () {
    $method = new ReflectionMethod(ProcessComposerRunner::class, 'scrubDiagnostics');
    $method->setAccessible(true);
    $diagnostics = 'to'.'ken='.str_repeat('t', 12)
        .' password: '.str_repeat('p', 12)
        .' Bearer '.str_repeat('b', 12)
        .' https://user:'.str_repeat('u', 12).'@example.test/path?'.'token='.str_repeat('q', 12)
        .' "token":"'.str_repeat('j', 12).'"';

    $scrubbed = $method->invoke(new ProcessComposerRunner, $diagnostics);

    expect($scrubbed)->toContain('[REDACTED]')
        ->and($scrubbed)->not->toContain(str_repeat('t', 12))
        ->and($scrubbed)->not->toContain(str_repeat('p', 12))
        ->and($scrubbed)->not->toContain(str_repeat('b', 12))
        ->and($scrubbed)->not->toContain(str_repeat('u', 12))
        ->and($scrubbed)->not->toContain(str_repeat('q', 12))
        ->and($scrubbed)->not->toContain(str_repeat('j', 12));
});

test('prepared purge journals recover package state before the next operation', function () {
    $installer = app(PackageInstaller::class);
    $package = $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));
    app(ModuleManager::class)->enable('sample');
    $package = $package->fresh();
    $lifecycle = DB::table('agovena_modules')->where('module_id', 'sample')->first();
    $fingerprintMethod = new ReflectionMethod(PackageInstaller::class, 'packageTreeFingerprint');
    $fingerprintMethod->setAccessible(true);
    $fingerprint = $fingerprintMethod->invoke($installer, $package->install_path);

    $snapshot = storage_path('app/packages/.purge-recovery.snapshot');
    File::copyDirectory($package->install_path, $snapshot);
    $destination = realpath($package->install_path);
    $journalRoot = storage_path('app/packages/.purge-operations');
    File::ensureDirectoryExists($journalRoot);
    $journal = $journalRoot.DIRECTORY_SEPARATOR.'purge-recovery.json';
    File::put($journal, json_encode([
        'status' => 'prepared',
        'kind' => PackageKind::Module->value,
        'agovena_id' => 'sample',
        'destination' => $destination,
        'snapshot' => $snapshot,
        'previous_state' => Crypt::encryptString(json_encode([
            'package' => $package->getAttributes(),
            'lifecycle' => $lifecycle === null ? null : (array) $lifecycle,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        'previous_fingerprint' => $fingerprint,
    ], JSON_THROW_ON_ERROR));

    File::deleteDirectory($package->install_path);
    DB::table('agovena_modules')->where('module_id', 'sample')->delete();
    DB::table('agovena_packages')->where('id', $package->id)->delete();

    $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));

    expect(is_dir($destination))->toBeTrue()
        ->and(AgovenaPackage::query()->where('agovena_id', 'sample')->exists())->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('sample'))->toBeTrue()
        ->and(File::exists($journal))->toBeFalse()
        ->and(File::exists($snapshot))->toBeFalse();
});

test('prepared purge journals discard incomplete snapshots without overwriting package files', function () {
    $installer = app(PackageInstaller::class);
    $package = $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));
    $destination = realpath($package->install_path);
    $fingerprintMethod = new ReflectionMethod(PackageInstaller::class, 'packageTreeFingerprint');
    $fingerprintMethod->setAccessible(true);
    $fingerprint = $fingerprintMethod->invoke($installer, $destination);
    $snapshot = storage_path('app/packages/.purge-incomplete.snapshot');
    File::ensureDirectoryExists($snapshot);
    File::put($snapshot.DIRECTORY_SEPARATOR.'module.json', 'incomplete');
    $journalRoot = storage_path('app/packages/.purge-operations');
    File::ensureDirectoryExists($journalRoot);
    $journal = $journalRoot.DIRECTORY_SEPARATOR.'purge-incomplete.json';
    File::put($journal, json_encode([
        'status' => 'prepared',
        'kind' => PackageKind::Module->value,
        'agovena_id' => 'sample',
        'destination' => $destination,
        'snapshot' => $snapshot,
        'snapshot_ready' => false,
        'previous_state' => Crypt::encryptString(json_encode([
            'package' => $package->getAttributes(),
            'lifecycle' => null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        'previous_fingerprint' => $fingerprint,
    ], JSON_THROW_ON_ERROR));

    $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));

    expect($fingerprintMethod->invoke($installer, $destination))->toBe($fingerprint)
        ->and(File::exists($journal))->toBeFalse()
        ->and(File::exists($snapshot))->toBeFalse();
});

test('orphaned purge snapshots are removed during package recovery', function () {
    $orphan = storage_path('app/packages/.purge.orphan.snapshot');
    File::ensureDirectoryExists($orphan);
    File::put($orphan.DIRECTORY_SEPARATOR.'orphan.txt', 'orphan');

    app(PackageInstaller::class)->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));

    expect(File::exists($orphan))->toBeFalse();
});

test('purge journals encrypt previous package state at rest', function () {
    $installer = app(PackageInstaller::class);
    $method = new ReflectionMethod(PackageInstaller::class, 'preparePurgeJournal');
    $method->setAccessible(true);

    $journal = $method->invoke(
        $installer,
        null,
        PackageKind::Module,
        'journal-encryption',
        [
            'source_locator' => 'https://user:plain-journal-value@example.test/repository',
        ],
        null,
        null,
    );

    $raw = File::get($journal);
    $payload = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    $state = json_decode(Crypt::decryptString($payload['previous_state']), true, flags: JSON_THROW_ON_ERROR);

    expect($payload)->toHaveKey('previous_state')
        ->and($raw)->not->toContain('plain-journal-value')
        ->and($state['package']['source_locator'])->toBe('https://user:plain-journal-value@example.test/repository');
});

test('prepared package operation journals recover package and lifecycle state without a component journal', function () {
    $installer = app(PackageInstaller::class);
    $package = $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Path,
        locator: sampleModulePath(),
    ));
    app(ModuleManager::class)->enable('sample');
    $package = $package->fresh();
    $lifecycle = DB::table('agovena_modules')->where('module_id', 'sample')->first();
    $fingerprintMethod = new ReflectionMethod(PackageInstaller::class, 'packageTreeFingerprint');
    $fingerprintMethod->setAccessible(true);
    $previousFingerprint = $fingerprintMethod->invoke($installer, $package->install_path);
    $prepareMethod = new ReflectionMethod(PackageInstaller::class, 'preparePackageOperationJournal');
    $prepareMethod->setAccessible(true);
    $updateMethod = new ReflectionMethod(PackageInstaller::class, 'updatePackageOperationJournal');
    $updateMethod->setAccessible(true);
    $recoverMethod = new ReflectionMethod(PackageInstaller::class, 'recoverPackageOperationJournal');
    $recoverMethod->setAccessible(true);

    $journal = $prepareMethod->invoke($installer, 'install', PackageKind::Module, 'sample');
    $updateMethod->invoke($installer, $journal, [
        'agovena_id' => 'sample',
        'destination' => $package->install_path,
        'previous_fingerprint' => $previousFingerprint,
        'previous_state' => Crypt::encryptString(json_encode([
            'package' => $package->getAttributes(),
            'lifecycle' => $lifecycle === null ? null : (array) $lifecycle,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ]);
    DB::table('agovena_packages')->where('id', $package->id)->update(['installed_version' => '9.9.9']);
    DB::table('agovena_modules')->where('module_id', 'sample')->update(['enabled' => false]);

    $recoverMethod->invoke($installer, $journal);

    expect(AgovenaPackage::query()->whereKey($package->id)->value('installed_version'))->toBe('1.0.0')
        ->and(DB::table('agovena_modules')->where('module_id', 'sample')->value('enabled'))->toBe(1)
        ->and(File::exists($journal))->toBeFalse();
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
        ->and(is_dir(storage_path('app/packages/modules/sample')))->toBeTrue()
        ->and(File::directories(storage_path('app/packages/uploads')))->toBe([]);
});

test('zip-installed packages do not expose an update action without a stable source', function () {
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
        $relative = 'sample/'.str_replace('\\\\', '/', substr($file->getPathname(), strlen($source) + 1));
        if ($file->isDir()) {
            $zip->addEmptyDir(rtrim($relative, '/'));
        } else {
            $zip->addFile($file->getPathname(), $relative);
        }
    }
    $zip->close();

    $installer = app(PackageInstaller::class);
    $package = $installer->install(new PackageSource(
        kind: PackageKind::Module,
        sourceType: PackageSourceType::Zip,
        locator: $zipPath,
    ));
    $package->available_version = '2.0.0';
    $package->save();

    expect($installer->hasUpdate($package->fresh() ?? $package))->toBeFalse()
        ->and(fn () => $installer->update(PackageKind::Module, 'sample'))
        ->toThrow(ValidationException::class);
});

test('prepared extension package operations restore encrypted settings state', function () {
    $installer = app(PackageInstaller::class);
    $settings = app(ExtensionSettingsRepository::class);
    $package = $installer->install(new PackageSource(
        kind: PackageKind::Extension,
        sourceType: PackageSourceType::Path,
        locator: sampleExtensionPath(),
    ));
    $settings->set('sample-gateway', 'mode', 'old-mode');
    $previousSettings = $settings->snapshot('sample-gateway');
    $lifecycle = DB::table('agovena_extensions')->where('extension_id', 'sample-gateway')->first();
    $fingerprintMethod = new ReflectionMethod(PackageInstaller::class, 'packageTreeFingerprint');
    $fingerprintMethod->setAccessible(true);
    $fingerprint = $fingerprintMethod->invoke($installer, $package->install_path);
    $prepareMethod = new ReflectionMethod(PackageInstaller::class, 'preparePackageOperationJournal');
    $prepareMethod->setAccessible(true);
    $journal = $prepareMethod->invoke($installer, 'install', PackageKind::Extension, 'sample-gateway');
    $updateMethod = new ReflectionMethod(PackageInstaller::class, 'updatePackageOperationJournal');
    $updateMethod->setAccessible(true);
    $updateMethod->invoke($installer, $journal, [
        'destination' => $package->install_path,
        'previous_fingerprint' => $fingerprint,
        'previous_state' => Crypt::encryptString(json_encode([
            'package' => $package->getAttributes(),
            'lifecycle' => $lifecycle === null ? null : (array) $lifecycle,
            'extension_settings' => $previousSettings,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
    ]);
    $settings->set('sample-gateway', 'mode', 'new-mode');
    $settings->set('sample-gateway', 'new-key', 'new-value');

    $installer->install(new PackageSource(
        kind: PackageKind::Extension,
        sourceType: PackageSourceType::Path,
        locator: sampleExtensionPath(),
    ));

    expect($settings->all('sample-gateway'))->toBe(['mode' => 'old-mode'])
        ->and(File::exists($journal))->toBeFalse();
});
