<?php

declare(strict_types=1);

namespace App\Agovena\Scaffolding;

use App\Agovena\Extensions\ExtensionCategory;
use App\Agovena\Packages\OptionalPackagesPath;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ScaffoldingGenerator
{
    public function __construct(private readonly Filesystem $files) {}

    public function module(string $id, bool $force): string
    {
        $this->assertSafeId($id);
        $root = ($this->optionalModulesRoot() ?? base_path('modules')).'/'.$id;
        $this->assertWritable($root, $force);
        $class = Str::studly($id);
        $namespace = 'Agovena\\Modules\\'.$class;

        $this->write($root.'/module.json', json_encode([
            'id' => $id,
            'name' => Str::headline($id),
            'version' => '0.1.0',
            'description' => '',
            'author' => '',
            'agovena' => '^0.1',
            'provider' => $namespace.'\\'.$class.'ServiceProvider',
            'dependencies' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $this->write($root.'/src/'.$class.'Module.php', $this->moduleClass($namespace, $class, $id));
        $this->write($root.'/src/'.$class.'ServiceProvider.php', $this->moduleProvider($namespace, $class));

        return $root;
    }

    public function extension(string $id, bool $force, string $category = 'other'): string
    {
        $this->assertSafeId($id);
        $resolvedCategory = $this->assertExtensionCategoryEnum($category);
        $categoryDir = $resolvedCategory->directoryName();
        $root = ($this->optionalExtensionsRoot() ?? base_path('extensions')).'/'.$categoryDir.'/'.$id;
        $this->assertWritable($root, $force);
        $class = Str::studly($id);
        $namespace = 'Agovena\\Extensions\\'.$class;

        $this->write($root.'/extension.json', json_encode([
            'id' => $id,
            'name' => Str::headline($id),
            'version' => '0.1.0',
            'description' => '',
            'author' => '',
            'category' => $resolvedCategory->value,
            'agovena' => '^0.1',
            'provider' => $namespace.'\\'.$class.'ServiceProvider',
            'dependencies' => [],
            'settings' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $this->write($root.'/src/'.$class.'Extension.php', $this->extensionClass($namespace, $class, $id));
        $this->write($root.'/src/'.$class.'ServiceProvider.php', $this->extensionProvider($namespace, $class));

        return $root;
    }

    public function theme(string $id, bool $force): string
    {
        $this->assertSafeId($id);
        $root = base_path('themes/'.$id);
        $this->assertWritable($root, $force);

        $this->write($root.'/theme.json', json_encode([
            'id' => $id,
            'name' => Str::headline($id),
            'version' => '0.1.0',
            'description' => '',
            'css' => null,
            'capabilities' => ['storefront'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $root;
    }

    private function assertSafeId(string $id): void
    {
        if (! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $id)) {
            throw new InvalidArgumentException('IDs must use lowercase letters, numbers, and single hyphens.');
        }
    }

    private function assertExtensionCategoryEnum(string $category): ExtensionCategory
    {
        $resolved = ExtensionCategory::tryFrom($category);
        if ($resolved === null) {
            $allowed = implode(', ', array_map(
                static fn (ExtensionCategory $case): string => $case->value,
                ExtensionCategory::cases(),
            ));

            throw new InvalidArgumentException("Unknown extension category [{$category}]. Use one of: {$allowed}.");
        }

        return $resolved;
    }

    private function assertWritable(string $root, bool $force): void
    {
        if ($this->files->exists($root) && ! $force) {
            throw new RuntimeException("{$root} already exists. Use --force to overwrite scaffold files.");
        }
    }

    private function write(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $contents);
    }

    private function optionalModulesRoot(): ?string
    {
        return OptionalPackagesPath::modulesRoot();
    }

    private function optionalExtensionsRoot(): ?string
    {
        return OptionalPackagesPath::extensionsRoot();
    }

    private function moduleClass(string $namespace, string $class, string $id): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Modules\ModuleContext;

final class {$class}Module implements Module
{
    public function id(): string
    {
        return '{$id}';
    }

    public function register(ModuleContext \$context): void {}
}
PHP;
    }

    private function moduleProvider(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use App\Agovena\Modules\Contracts\Module;
use Illuminate\Support\ServiceProvider;

final class {$class}ServiceProvider extends ServiceProvider
{
    public function module(): Module
    {
        return new {$class}Module;
    }
}
PHP;
    }

    private function extensionClass(string $namespace, string $class, string $id): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use App\Agovena\Extensions\Contracts\Extension;
use App\Agovena\Extensions\ExtensionContext;

final class {$class}Extension implements Extension
{
    public function id(): string
    {
        return '{$id}';
    }

    public function register(ExtensionContext \$context): void {}
}
PHP;
    }

    private function extensionProvider(string $namespace, string $class): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use App\Agovena\Extensions\Contracts\Extension;
use Illuminate\Support\ServiceProvider;

final class {$class}ServiceProvider extends ServiceProvider
{
    public function extension(): Extension
    {
        return new {$class}Extension;
    }
}
PHP;
    }
}
