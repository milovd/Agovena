<?php

declare(strict_types=1);

use App\Agovena\Installation\EnsurePublicStorageLink;
use Illuminate\Support\Facades\File;

test('ensure public storage link succeeds when link already exists', function () {
    $root = storage_path('framework/testing/storage-link-'.uniqid('existing_', true));
    $target = $root.DIRECTORY_SEPARATOR.'target';
    $link = $root.DIRECTORY_SEPARATOR.'link';

    File::ensureDirectoryExists($target);
    app('files')->link($target, $link);

    $service = new EnsurePublicStorageLink($link, $target);

    expect($service->exists())->toBeTrue()
        ->and($service->ensure())->toBeTrue()
        ->and($service->exists())->toBeTrue();

    @unlink($link);
    File::deleteDirectory($root);
});

test('ensure public storage link creates a missing link', function () {
    $root = storage_path('framework/testing/storage-link-'.uniqid('create_', true));
    $target = $root.DIRECTORY_SEPARATOR.'target';
    $link = $root.DIRECTORY_SEPARATOR.'link';

    File::ensureDirectoryExists($target);

    $service = new EnsurePublicStorageLink($link, $target);

    expect($service->exists())->toBeFalse()
        ->and($service->ensure())->toBeTrue()
        ->and($service->exists())->toBeTrue();

    @unlink($link);
    File::deleteDirectory($root);
});

test('ensure public storage link replaces a stale link to a missing target', function () {
    $root = storage_path('framework/testing/storage-link-'.uniqid('stale_', true));
    $oldTarget = $root.DIRECTORY_SEPARATOR.'old-target';
    $target = $root.DIRECTORY_SEPARATOR.'target';
    $link = $root.DIRECTORY_SEPARATOR.'link';

    File::ensureDirectoryExists($oldTarget);
    File::ensureDirectoryExists($target);
    app('files')->link($oldTarget, $link);
    File::deleteDirectory($oldTarget);

    $service = new EnsurePublicStorageLink($link, $target);

    expect($service->exists())->toBeFalse()
        ->and($service->ensure())->toBeTrue()
        ->and($service->exists())->toBeTrue();

    @unlink($link);
    File::deleteDirectory($root);
});

test('ensure public storage link fails when a conflicting path blocks creation', function () {
    $root = storage_path('framework/testing/storage-link-'.uniqid('fail_', true));
    $target = $root.DIRECTORY_SEPARATOR.'target';
    $link = $root.DIRECTORY_SEPARATOR.'link';

    File::ensureDirectoryExists($target);
    File::ensureDirectoryExists($link); // conflict: real directory, not a link to target

    $service = new EnsurePublicStorageLink($link, $target);

    expect($service->exists())->toBeFalse()
        ->and($service->ensure())->toBeFalse()
        ->and($service->exists())->toBeFalse();

    File::deleteDirectory($root);
});
