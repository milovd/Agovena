<?php

declare(strict_types=1);

namespace App\Agovena\Modules;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Customer\CustomerAccountOverview;
use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Packages\PackageAutoload;
use App\Agovena\Support\RecoversTestTransaction;
use App\Models\AgovenaModule;
use Composer\Semver\Semver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ModuleManager
{
    /** @var array<string, ModuleManifest>|null */
    private ?array $discovered = null;

    /** @var array<string, true> */
    private array $booted = [];

    public function __construct(
        private readonly Application $app,
        private readonly AdminRegistrar $admin,
        private readonly ProductCapabilityRegistry $capabilities,
        private readonly CustomerAccountNav $customerAccountNav,
        private readonly CustomerAccountOverview $customerAccountOverview,
        private readonly Dispatcher $events,
        private readonly PackageAutoload $autoload,
    ) {}

    public function refresh(): void
    {
        $this->discovered = null;
        $this->booted = [];
    }

    /**
     * @return list<ModuleManifest>
     */
    public function discover(): array
    {
        return array_values($this->discoveredMap());
    }

    public function manifest(string $moduleId): ?ModuleManifest
    {
        return $this->discoveredMap()[$moduleId] ?? null;
    }

    public function isEnabled(string $moduleId): bool
    {
        if (! $this->modulesTableReady()) {
            return false;
        }

        return AgovenaModule::query()
            ->where('module_id', $moduleId)
            ->where('enabled', true)
            ->exists();
    }

    public function isInstalled(string $moduleId): bool
    {
        if (! $this->modulesTableReady()) {
            return false;
        }

        return AgovenaModule::query()->where('module_id', $moduleId)->exists();
    }

    /**
     * Boot providers + Module::register for every enabled module.
     */
    public function bootEnabled(): void
    {
        if (! $this->modulesTableReady()) {
            return;
        }

        foreach ($this->discover() as $manifest) {
            if (! $this->isEnabled($manifest->id)) {
                continue;
            }

            $this->bootManifest($manifest);
        }
    }

    public function install(string $moduleId): AgovenaModule
    {
        $manifest = $this->requireManifest($moduleId);
        $this->assertCompatible($manifest);
        $this->assertDependencies($manifest, requireEnabled: false);

        $row = AgovenaModule::query()->firstOrNew(['module_id' => $moduleId]);
        $row->version = $manifest->version;
        $row->installed_at ??= now();
        $row->enabled = false;
        $row->save();

        $this->runModuleMigrations($manifest);

        return $row->fresh() ?? $row;
    }

    public function enable(string $moduleId): AgovenaModule
    {
        $manifest = $this->requireManifest($moduleId);
        $this->assertCompatible($manifest);
        $this->assertDependencies($manifest, requireEnabled: true);

        $row = AgovenaModule::query()->where('module_id', $moduleId)->first();
        if ($row === null) {
            $row = $this->install($moduleId);
        }

        $this->runModuleMigrations($manifest);

        $row->enabled = true;
        $row->version = $manifest->version;
        $row->enabled_at = now();
        $row->disabled_at = null;
        $row->save();

        $this->bootManifest($manifest);

        return $row->fresh() ?? $row;
    }

    /**
     * Disable removes the module from the runtime. Data/tables are preserved.
     */
    public function disable(string $moduleId): AgovenaModule
    {
        $row = AgovenaModule::query()->where('module_id', $moduleId)->firstOrFail();
        $row->enabled = false;
        $row->disabled_at = now();
        $row->save();

        return $row;
    }

    /**
     * Apply pending migrations for every installed Module (enabled or disabled).
     * Used by `agovena:upgrade` so pulled Module schema changes land without enable/disable.
     */
    public function migrateInstalled(): void
    {
        if (! $this->modulesTableReady()) {
            return;
        }

        foreach ($this->discover() as $manifest) {
            if (! $this->isInstalled($manifest->id)) {
                continue;
            }

            $this->runModuleMigrations($manifest);
        }
    }

    /**
     * Explicit uninstall marker. Does not drop module tables by default.
     */
    public function uninstall(string $moduleId, bool $purgeData = false): void
    {
        if ($purgeData) {
            throw ValidationException::withMessages([
                'module' => __('admin.modules.purge_not_implemented'),
            ]);
        }

        $row = AgovenaModule::query()->where('module_id', $moduleId)->first();
        if ($row === null) {
            return;
        }

        if ($row->enabled) {
            $this->disable($moduleId);
        }

        $row->delete();
    }

    /**
     * @return array{manifest: ModuleManifest, record: AgovenaModule|null, enabled: bool, installed: bool, compatible: bool, compatibility_error: string|null}
     */
    public function status(string $moduleId): array
    {
        $manifest = $this->requireManifest($moduleId);
        $record = $this->modulesTableReady()
            ? AgovenaModule::query()->where('module_id', $moduleId)->first()
            : null;

        $compatible = true;
        $compatibilityError = null;
        try {
            $this->assertCompatible($manifest);
        } catch (ValidationException $e) {
            $compatible = false;
            $compatibilityError = $e->errors()['module'][0] ?? $e->getMessage();
        }

        return [
            'manifest' => $manifest,
            'record' => $record,
            'enabled' => $record?->enabled === true,
            'installed' => $record !== null,
            'compatible' => $compatible,
            'compatibility_error' => $compatibilityError,
        ];
    }

    private function bootManifest(ModuleManifest $manifest): void
    {
        if (isset($this->booted[$manifest->id])) {
            return;
        }

        if (! class_exists($manifest->provider)) {
            throw new RuntimeException("Module provider [{$manifest->provider}] not found for [{$manifest->id}].");
        }

        if (! $this->app->providerIsLoaded($manifest->provider)) {
            $this->app->register($manifest->provider);
        }

        $module = $this->resolveModuleInstance($manifest);
        $context = new ModuleContext(
            $this->admin,
            $this->capabilities,
            $this->customerAccountNav,
            $this->customerAccountOverview,
            $this->events,
            $manifest->id,
        );
        $module->register($context);

        $this->booted[$manifest->id] = true;
    }

    private function resolveModuleInstance(ModuleManifest $manifest): Module
    {
        $providerClass = $manifest->provider;
        if (is_a($providerClass, Module::class, true)) {
            /** @var Module $module */
            $module = $this->app->make($providerClass);

            return $module;
        }

        $provider = $this->app->getProvider($providerClass);
        if ($provider !== null && method_exists($provider, 'module')) {
            $module = $provider->module();
            if ($module instanceof Module) {
                return $module;
            }
        }

        throw new RuntimeException("Unable to resolve Module contract for [{$manifest->id}].");
    }

    private function runModuleMigrations(ModuleManifest $manifest): void
    {
        $path = $manifest->path.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (! is_dir($path)) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $this->relativePath($path),
            '--force' => true,
        ]);
        RecoversTestTransaction::afterDdl();
    }

    private function relativePath(string $absolute): string
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolute);
        if (str_starts_with($normalized, $base)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($normalized, strlen($base)));
        }

        return $absolute;
    }

    private function assertDependencies(ModuleManifest $manifest, bool $requireEnabled): void
    {
        foreach ($manifest->dependencies as $dependency) {
            if ($this->manifest($dependency) === null) {
                throw ValidationException::withMessages([
                    'module' => __('admin.modules.missing_dependency', [
                        'module' => $manifest->id,
                        'dependency' => $dependency,
                    ]),
                ]);
            }

            if ($requireEnabled && ! $this->isEnabled($dependency)) {
                throw ValidationException::withMessages([
                    'module' => __('admin.modules.dependency_disabled', [
                        'module' => $manifest->id,
                        'dependency' => $dependency,
                    ]),
                ]);
            }
        }
    }

    private function requireManifest(string $moduleId): ModuleManifest
    {
        $manifest = $this->manifest($moduleId);
        if ($manifest === null) {
            throw ValidationException::withMessages([
                'module' => __('admin.modules.not_found', ['module' => $moduleId]),
            ]);
        }

        return $manifest;
    }

    private function assertCompatible(ModuleManifest $manifest): void
    {
        $platform = (string) config('agovena.version', '0.1.0');
        $constraint = $manifest->agovena;
        if ($constraint === '*' || $constraint === '') {
            return;
        }

        if (! Semver::satisfies($platform, $constraint)) {
            throw ValidationException::withMessages([
                'module' => __('admin.packages.incompatible', [
                    'constraint' => $constraint,
                    'platform' => $platform,
                ]),
            ]);
        }
    }

    /**
     * @return array<string, ModuleManifest>
     */
    private function discoveredMap(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $this->discovered = [];
        foreach ($this->scanRoots() as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (File::directories($root) as $directory) {
                $file = $directory.DIRECTORY_SEPARATOR.'module.json';
                if (! is_file($file)) {
                    continue;
                }

                /** @var array<string, mixed>|null $data */
                $data = json_decode((string) File::get($file), true);
                if (! is_array($data) || ! isset($data['id'], $data['name'], $data['provider'])) {
                    continue;
                }

                $manifest = ModuleManifest::fromArray($data, $directory);
                if (isset($this->discovered[$manifest->id])) {
                    continue;
                }

                $this->autoload->register($manifest->path, $this->autoloadMap($manifest));
                $this->discovered[$manifest->id] = $manifest;
            }
        }

        return $this->discovered;
    }

    /**
     * @return list<string>
     */
    private function scanRoots(): array
    {
        $roots = [base_path('modules'), storage_path('app/packages/modules')];
        foreach (config('agovena.packages.extra_module_paths', []) as $path) {
            if (is_string($path) && $path !== '') {
                $roots[] = $path;
            }
        }

        return $roots;
    }

    /**
     * @return array<string, string>
     */
    private function autoloadMap(ModuleManifest $manifest): array
    {
        if ($manifest->autoloadPsr4 !== []) {
            return $manifest->autoloadPsr4;
        }

        $ns = substr($manifest->provider, 0, (int) strrpos($manifest->provider, '\\') + 1);
        $src = is_dir($manifest->path.DIRECTORY_SEPARATOR.'src') ? 'src/' : '';

        return $ns !== '' ? [$ns => $src] : [];
    }

    private function modulesTableReady(): bool
    {
        try {
            return Schema::hasTable('agovena_modules');
        } catch (\Throwable) {
            return false;
        }
    }
}
