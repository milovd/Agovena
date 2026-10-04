<?php

declare(strict_types=1);

test('core application code outside the local demo fixture does not import module implementation classes', function () {
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

        $relativePath = str_replace('\\', '/', str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname()));
        // This non-production fixture needs optional module models for their casts and encrypted fields.
        if ($relativePath === 'Console/Commands/AgovenaSeedDemoCommand.php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        if (preg_match('/(?<!App\\\\)Agovena\\\\Modules\\\\/', $contents) === 1) {
            $violations[] = $relativePath;
        }
    }

    expect($violations)->toBe([]);
});
