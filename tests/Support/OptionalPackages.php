<?php

declare(strict_types=1);

use App\Agovena\Packages\OptionalPackagesPath;

function optionalPackagesRoot(): string
{
    $root = OptionalPackagesPath::root();
    if ($root === null) {
        throw new RuntimeException('AGOVENA_OPTIONAL_PACKAGES_PATH is not configured for tests.');
    }

    return $root;
}

function optionalModuleRoot(?string $moduleId = null): string
{
    $root = optionalPackagesRoot().DIRECTORY_SEPARATOR.'modules';
    if ($moduleId === null) {
        return $root;
    }

    return $root.DIRECTORY_SEPARATOR.$moduleId;
}

function optionalExtensionRoot(?string $category = null, ?string $extensionId = null): string
{
    $root = optionalPackagesRoot().DIRECTORY_SEPARATOR.'extensions';
    if ($category === null) {
        return $root;
    }

    $path = $root.DIRECTORY_SEPARATOR.$category;
    if ($extensionId === null) {
        return $path;
    }

    return $path.DIRECTORY_SEPARATOR.$extensionId;
}
