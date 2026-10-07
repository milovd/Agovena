<?php

declare(strict_types=1);

use App\Agovena\Packages\OptionalPackagesPath;

/*
 * Module Admin views moved into each optional Module under its own view
 * namespace (for example provisioning::admin.show). Operators install
 * packages from optional-packages main by default and installed packages
 * only change when they are updated, so Modules released before that move
 * still render the old core names. Core keeps these copies until every
 * supported optional-packages release ships its own views.
 */

/**
 * Legacy core view => [module directory, module view namespace, module view].
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function legacyModuleAdminViews(): array
{
    return [
        'livewire.admin.digital.assets-index' => ['downloads', 'digital', 'admin.assets-index'],
        'livewire.admin.digital.customer-section' => ['downloads', 'digital', 'admin.customer-section'],
        'livewire.admin.digital-delivery.secrets-index' => ['digital-delivery', 'digital-delivery', 'admin.secrets-index'],
        'livewire.admin.digital-delivery.customer-section' => ['digital-delivery', 'digital-delivery', 'admin.customer-section'],
        'livewire.admin.domains.index' => ['domains', 'domains', 'admin.index'],
        'livewire.admin.events.index' => ['events', 'events', 'admin.index'],
        'livewire.admin.events.show' => ['events', 'events', 'admin.show'],
        'livewire.admin.events.check-in' => ['events', 'events', 'admin.check-in'],
        'livewire.admin.events.customer-section' => ['events', 'events', 'admin.customer-section'],
        'livewire.admin.events.product-tab' => ['events', 'events', 'admin.product-tab'],
        'livewire.admin.provisioning.index' => ['provisioning', 'provisioning', 'admin.index'],
        'livewire.admin.provisioning.show' => ['provisioning', 'provisioning', 'admin.show'],
        'livewire.admin.provisioning.servers' => ['provisioning', 'provisioning', 'admin.servers'],
        'livewire.admin.provisioning.customer-section' => ['provisioning', 'provisioning', 'admin.customer-section'],
    ];
}

test('core keeps the Admin views that Modules released before package-owned views still render', function () {
    foreach (array_keys(legacyModuleAdminViews()) as $view) {
        expect(view()->exists($view))->toBeTrue("{$view} must stay in core for released Modules");
    }
});

test('legacy core module views stay identical to the views the Modules now ship', function () {
    $root = OptionalPackagesPath::modulesRoot();
    if ($root === null) {
        $this->markTestSkipped('AGOVENA_OPTIONAL_PACKAGES_PATH is not configured.');
    }

    $compared = 0;
    foreach (legacyModuleAdminViews() as $legacy => [$module, $namespace, $view]) {
        $packaged = $root.'/'.$module.'/resources/views/'.str_replace('.', '/', $view).'.blade.php';
        if (! is_file($packaged)) {
            // This optional-packages revision predates package-owned views and renders the legacy name.
            continue;
        }

        expect(file_get_contents(resource_path('views/'.str_replace('.', '/', $legacy).'.blade.php')))
            ->toBe(file_get_contents($packaged), "{$legacy} drifted from {$namespace}::{$view}");
        $compared++;
    }

    if ($compared === 0) {
        $this->markTestSkipped('This optional-packages revision predates package-owned Module views.');
    }
});

test('every view an optional package renders by name resolves against this core', function () {
    $root = OptionalPackagesPath::root();
    if ($root === null) {
        $this->markTestSkipped('AGOVENA_OPTIONAL_PACKAGES_PATH is not configured.');
    }

    $namespaces = [];
    $sources = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR)) {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        $sources[$file->getPathname()] = $source;
        if (preg_match("/loadViewsFrom\\((.+),\\s*'([a-z-]+)'\\)/", $source, $match) === 1) {
            $packageRoot = dirname($file->getPath());
            $namespaces[$match[2]] = $packageRoot.'/resources/views';
        }
    }

    $missing = [];
    $checked = 0;
    foreach ($sources as $path => $source) {
        preg_match_all("/\\bview\\('([a-z0-9._:-]+)'/", $source, $matches);
        foreach ($matches[1] as $view) {
            $checked++;
            if (str_contains($view, '::')) {
                [$namespace, $name] = explode('::', $view, 2);
                $resolves = isset($namespaces[$namespace])
                    && is_file($namespaces[$namespace].'/'.str_replace('.', '/', $name).'.blade.php');
            } else {
                $resolves = view()->exists($view);
            }
            if (! $resolves) {
                $missing[] = "{$view} (rendered by {$path})";
            }
        }
    }

    expect($checked)->toBeGreaterThan(0)
        ->and($missing)->toBe([]);
});
