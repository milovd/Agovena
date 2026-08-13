<?php

declare(strict_types=1);

test('core application code does not import module implementation classes', function () {
    $root = base_path('app');
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        if (preg_match('/(?<!App\\\\)Agovena\\\\Modules\\\\/', $contents) === 1) {
            $violations[] = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($violations)->toBe([]);
});
