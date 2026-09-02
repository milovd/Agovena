<?php

declare(strict_types=1);

use App\Agovena\Packages\PackageAutoload;

it('loads package classes when the Composer loader is unavailable', function (): void {
    class_exists(PackageAutoload::class);

    $autoloaders = spl_autoload_functions();
    $fallbackAutoloaders = [];
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'agovena-package-autoload-'.bin2hex(random_bytes(8));
    $source = $root.DIRECTORY_SEPARATOR.'src';
    $namespace = 'PackageAutoloadProbe'.bin2hex(random_bytes(4));
    $class = $namespace.'\\Probe';
    $file = $source.DIRECTORY_SEPARATOR.'Probe.php';

    mkdir($source, 0777, true);
    file_put_contents($file, "<?php\nnamespace {$namespace};\nfinal class Probe {}\n");

    foreach ($autoloaders as $autoload) {
        spl_autoload_unregister($autoload);
    }

    try {
        (new PackageAutoload)->register($root, [$namespace.'\\' => 'src']);
        $fallbackAutoloaders = array_values(array_filter(
            spl_autoload_functions(),
            static fn ($autoload): bool => ! in_array($autoload, $autoloaders, true),
        ));

        $loaded = class_exists($class);
    } finally {
        foreach ($fallbackAutoloaders as $autoload) {
            spl_autoload_unregister($autoload);
        }

        foreach ($autoloaders as $autoload) {
            spl_autoload_register($autoload);
        }

        @unlink($file);
        @rmdir($source);
        @rmdir($root);
    }

    expect($loaded)->toBeTrue();
});
