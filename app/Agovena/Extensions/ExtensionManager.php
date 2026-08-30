<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Checkout\CartRequirementComposer;
use App\Agovena\Extensions\Contracts\Extension;
use App\Agovena\Invoices\InvoiceDocumentView;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\OptionalPackagesPath;
use App\Agovena\Packages\PackageAutoload;
use App\Agovena\Packages\PackageMigrationRunner;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Shipping\ShippingCarrierRegistry;
use App\Models\AgovenaExtension;
use Composer\Semver\Semver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ExtensionManager
{
    /** @var array<string, ExtensionManifest>|null */
    private ?array $discovered = null;

    /** @var array<string, true> */
    private array $booted = [];

    /** @var array<string, ExtensionContext> */
    private array $contexts = [];

    public function __construct(
        private readonly Application $app,
        private readonly AdminRegistrar $admin,
        private readonly PaymentGatewayRegistry $paymentGateways,
        private readonly ProvisionerRegistry $provisioners,
        private readonly ShippingCarrierRegistry $shippingCarriers,
        private readonly ExtensionSettingsRepository $settings,
        private readonly PackageAutoload $autoload,
        private readonly CartRequirementComposer $cartRequirements,
        private readonly InvoiceDocumentView $invoiceDocumentView,
        private readonly ModuleManager $modules,
        private readonly PackageMigrationRunner $migrations,
        private readonly RuntimeRegistry $runtimeRegistries,
    ) {}

    public function refresh(): void
    {
        $this->discovered = null;
        $this->booted = [];
        $this->contexts = [];
    }

    /**
     * @return list<ExtensionManifest>
     */
    public function discover(): array
    {
        return array_values($this->discoveredMap());
    }

    public function manifest(string $extensionId): ?ExtensionManifest
    {
        return $this->discoveredMap()[$extensionId] ?? null;
    }

    public function isEnabled(string $extensionId): bool
    {
        if (! $this->extensionsTableReady()) {
            return false;
        }

        return AgovenaExtension::query()
            ->where('extension_id', $extensionId)
            ->where('enabled', true)
            ->exists();
    }

    public function isInstalled(string $extensionId): bool
    {
        if (! $this->extensionsTableReady()) {
            return false;
        }

        return AgovenaExtension::query()->where('extension_id', $extensionId)->exists();
    }

    public function bootEnabled(): void
    {
        if (! $this->extensionsTableReady()) {
            return;
        }

        foreach ($this->discover() as $manifest) {
            if (! $this->isEnabled($manifest->id) || ! $this->canUseManifest($manifest)) {
                continue;
            }

            try {
                $this->assertModuleDependencies($manifest, requireEnabled: true);
            } catch (ValidationException) {
                continue;
            }

            $this->bootManifest($manifest);
        }
    }

    public function install(string $extensionId, ?string $migrationJournal = null): AgovenaExtension
    {
        $manifest = $this->requireManifest($extensionId);
        $this->assertCompatible($manifest);
        $this->assertDependencies($manifest, requireEnabled: false);
        $this->assertModuleDependencies($manifest, requireEnabled: false);

        $row = AgovenaExtension::query()->firstOrNew(['extension_id' => $extensionId]);
        $row->version = $manifest->version;
        $row->installed_at ??= now();
        $row->enabled = false;
        $row->save();

        try {
            $this->runExtensionMigrations($manifest, $migrationJournal);
            $this->seedDefaultSettings($manifest);
        } catch (\Throwable $exception) {
            $row->delete();
            throw $exception;
        }

        return $row->fresh() ?? $row;
    }

    public function enable(string $extensionId): AgovenaExtension
    {
        $manifest = $this->requireManifest($extensionId);
        $this->assertCompatible($manifest);
        $this->assertDependencies($manifest, requireEnabled: true);
        $this->assertModuleDependencies($manifest, requireEnabled: true);

        $row = AgovenaExtension::query()->where('extension_id', $extensionId)->first();
        if ($row === null) {
            throw ValidationException::withMessages([
                'extension' => __('admin.extensions.install_before_enable', ['extension' => $extensionId]),
            ]);
        }

        $this->runExtensionMigrations($manifest);

        $row->enabled = true;
        $row->version = $manifest->version;
        $row->enabled_at = now();
        $row->disabled_at = null;
        $row->save();

        $this->bootManifest($manifest);

        return $row->fresh() ?? $row;
    }

    /**
     * Disable removes the extension from the runtime. Settings/data are preserved.
     */
    public function disable(string $extensionId): AgovenaExtension
    {
        $row = AgovenaExtension::query()->where('extension_id', $extensionId)->firstOrFail();
        $row->enabled = false;
        $row->disabled_at = now();
        $row->save();

        $this->rebuildRuntime();

        return $row;
    }

    /**
     * Apply pending migrations for every installed extension (enabled or disabled).
     * Used by `agovena:upgrade` so pulled extension schema changes land without enable/disable.
     */
    public function migrateInstalled(): void
    {
        if (! $this->extensionsTableReady()) {
            return;
        }

        $this->migrations->reconcile();

        foreach ($this->discover() as $manifest) {
            if (! $this->isInstalled($manifest->id)) {
                continue;
            }

            $this->runExtensionMigrations($manifest);
        }
    }

    /**
     * Clear provider registries and re-register enabled Extensions only.
     */
    public function rebuildRuntime(): void
    {
        $this->paymentGateways->clear();
        $this->provisioners->clear();
        $this->shippingCarriers->clear();
        $this->runtimeRegistries->clear();
        $this->booted = [];
        $this->contexts = [];
        $this->bootEnabled();
    }

    public function uninstall(string $extensionId, bool $purgeData = false): void
    {
        if ($purgeData) {
            throw ValidationException::withMessages([
                'extension' => __('admin.extensions.purge_not_implemented'),
            ]);
        }

        $row = AgovenaExtension::query()->where('extension_id', $extensionId)->first();
        if ($row === null) {
            return;
        }

        if ($row->enabled) {
            $this->disable($extensionId);
        }

        $row->delete();
    }

    /**
     * @return array{
     *     manifest: ExtensionManifest,
     *     record: AgovenaExtension|null,
     *     enabled: bool,
     *     installed: bool,
     *     compatible: bool,
     *     compatibility_error: string|null
     * }
     */
    public function status(string $extensionId): array
    {
        $manifest = $this->requireManifest($extensionId);
        $record = $this->extensionsTableReady()
            ? AgovenaExtension::query()->where('extension_id', $extensionId)->first()
            : null;

        $compatible = true;
        $compatibilityError = null;
        try {
            $this->assertCompatible($manifest);
        } catch (ValidationException $e) {
            $compatible = false;
            $compatibilityError = $e->errors()['extension'][0] ?? $e->getMessage();
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

    public function context(string $extensionId): ?ExtensionContext
    {
        return $this->contexts[$extensionId] ?? null;
    }

    private function bootManifest(ExtensionManifest $manifest): void
    {
        if (isset($this->booted[$manifest->id])) {
            return;
        }

        if (! class_exists($manifest->provider)) {
            throw new RuntimeException("Extension provider [{$manifest->provider}] not found for [{$manifest->id}].");
        }

        if (! $this->app->providerIsLoaded($manifest->provider)) {
            $this->app->register($manifest->provider);
        }

        $extension = $this->resolveExtensionInstance($manifest);
        $context = new ExtensionContext(
            $manifest->id,
            $this->admin,
            $this->paymentGateways,
            $this->provisioners,
            $this->shippingCarriers,
            $this->settings,
            $this->cartRequirements,
            $this->invoiceDocumentView,
        );
        $extension->register($context);

        $this->contexts[$manifest->id] = $context;
        $this->booted[$manifest->id] = true;
    }

    private function resolveExtensionInstance(ExtensionManifest $manifest): Extension
    {
        $providerClass = $manifest->provider;
        if (is_a($providerClass, Extension::class, true)) {
            /** @var Extension $extension */
            $extension = $this->app->make($providerClass);

            return $extension;
        }

        $provider = $this->app->getProvider($providerClass);
        if ($provider !== null && method_exists($provider, 'extension')) {
            $extension = $provider->extension();
            if ($extension instanceof Extension) {
                return $extension;
            }
        }

        throw new RuntimeException("Unable to resolve Extension contract for [{$manifest->id}].");
    }

    private function seedDefaultSettings(ExtensionManifest $manifest): void
    {
        foreach ($manifest->settings as $setting) {
            $key = $setting['key'];
            $existing = $this->settings->get($manifest->id, $key, new \stdClass);
            if (! $existing instanceof \stdClass) {
                continue;
            }
            if (! array_key_exists('default', $setting) || $setting['default'] === null) {
                continue;
            }
            $this->settings->set(
                $manifest->id,
                $key,
                $setting['default'],
                (bool) ($setting['secret'] ?? false),
            );
        }
    }

    private function runExtensionMigrations(ExtensionManifest $manifest, ?string $migrationJournal = null): void
    {
        $this->migrations->run($manifest->id, $manifest->path, $migrationJournal);
    }

    private function assertCompatible(ExtensionManifest $manifest): void
    {
        if (! $this->canUseManifest($manifest)) {
            throw ValidationException::withMessages([
                'extension' => __('admin.extensions.not_production_ready', [
                    'extension' => $manifest->id,
                ]),
            ]);
        }

        $platform = (string) config('agovena.version', '0.1.0');
        $constraint = $manifest->agovena;
        if ($constraint === '*' || $constraint === '') {
            return;
        }

        if (! Semver::satisfies($platform, $constraint)) {
            throw ValidationException::withMessages([
                'extension' => __('admin.extensions.incompatible', [
                    'extension' => $manifest->id,
                    'constraint' => $constraint,
                    'platform' => $platform,
                ]),
            ]);
        }
    }

    private function canUseManifest(ExtensionManifest $manifest): bool
    {
        return $manifest->productionReady || app()->environment(['local', 'testing']);
    }

    private function assertDependencies(ExtensionManifest $manifest, bool $requireEnabled): void
    {
        foreach ($manifest->dependencies as $dependency) {
            if ($this->manifest($dependency) === null) {
                throw ValidationException::withMessages([
                    'extension' => __('admin.extensions.missing_dependency', [
                        'extension' => $manifest->id,
                        'dependency' => $dependency,
                    ]),
                ]);
            }

            if ($requireEnabled && ! $this->isEnabled($dependency)) {
                throw ValidationException::withMessages([
                    'extension' => __('admin.extensions.dependency_disabled', [
                        'extension' => $manifest->id,
                        'dependency' => $dependency,
                    ]),
                ]);
            }
        }
    }

    private function assertModuleDependencies(ExtensionManifest $manifest, bool $requireEnabled): void
    {
        foreach ($manifest->moduleDependencies as $dependency) {
            if ($this->modules->manifest($dependency) === null) {
                throw ValidationException::withMessages([
                    'extension' => __('admin.extensions.missing_module_dependency', [
                        'extension' => $manifest->id,
                        'module' => $dependency,
                    ]),
                ]);
            }

            $satisfied = $requireEnabled
                ? $this->modules->isEnabled($dependency)
                : $this->modules->isInstalled($dependency);
            if (! $satisfied) {
                throw ValidationException::withMessages([
                    'extension' => __($requireEnabled
                        ? 'admin.extensions.module_dependency_disabled'
                        : 'admin.extensions.module_dependency_not_installed', [
                            'extension' => $manifest->id,
                            'module' => $dependency,
                        ]),
                ]);
            }
        }
    }

    private function requireManifest(string $extensionId): ExtensionManifest
    {
        $manifest = $this->manifest($extensionId);
        if ($manifest === null) {
            throw ValidationException::withMessages([
                'extension' => __('admin.extensions.not_found', ['extension' => $extensionId]),
            ]);
        }

        return $manifest;
    }

    /**
     * @return array<string, ExtensionManifest>
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

            foreach ($this->packageDirectories($root) as $directory) {
                $file = $directory.DIRECTORY_SEPARATOR.'extension.json';
                if (! is_file($file)) {
                    continue;
                }

                /** @var array<string, mixed>|null $data */
                $data = json_decode((string) File::get($file), true);
                if (! is_array($data)) {
                    continue;
                }

                try {
                    $manifest = ExtensionManifest::fromArray($data, $directory);
                } catch (\InvalidArgumentException) {
                    continue;
                }

                if (isset($this->discovered[$manifest->id])) {
                    continue;
                }

                $this->discovered[$manifest->id] = $manifest;
                $this->autoload->register($manifest->path, $this->autoloadMap($manifest));
            }
        }

        return $this->discovered;
    }

    /**
     * Package roots may be flat (`extensions/{id}`) or category-organized
     * (`extensions/{category}/{id}`). Identity always comes from extension.json `id`.
     *
     * @return list<string>
     */
    private function packageDirectories(string $root): array
    {
        $packages = [];
        foreach (File::directories($root) as $directory) {
            if (is_file($directory.DIRECTORY_SEPARATOR.'extension.json')) {
                $packages[] = $directory;

                continue;
            }

            foreach (File::directories($directory) as $nested) {
                if (is_file($nested.DIRECTORY_SEPARATOR.'extension.json')) {
                    $packages[] = $nested;
                }
            }
        }

        return $packages;
    }

    /**
     * @return list<string>
     */
    private function scanRoots(): array
    {
        $roots = [storage_path('app/packages/extensions')];

        $optionalRoot = OptionalPackagesPath::extensionsRoot();
        if ($optionalRoot !== null) {
            $roots[] = $optionalRoot;
        }

        foreach (config('agovena.packages.extra_extension_paths', []) as $path) {
            if (is_string($path) && $path !== '') {
                $roots[] = $path;
            }
        }

        return $roots;
    }

    /**
     * @return array<string, string>
     */
    private function autoloadMap(ExtensionManifest $manifest): array
    {
        if ($manifest->autoloadPsr4 !== []) {
            return $manifest->autoloadPsr4;
        }

        $ns = substr($manifest->provider, 0, (int) strrpos($manifest->provider, '\\') + 1);
        $src = is_dir($manifest->path.DIRECTORY_SEPARATOR.'src') ? 'src/' : '';

        return $ns !== '' ? [$ns => $src] : [];
    }

    private function extensionsTableReady(): bool
    {
        try {
            return Schema::hasTable('agovena_extensions');
        } catch (\Throwable) {
            return false;
        }
    }
}
