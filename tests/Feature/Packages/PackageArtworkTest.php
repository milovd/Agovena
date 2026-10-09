<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionManifest;
use App\Agovena\Modules\ModuleManifest;
use App\Agovena\Packages\PackageArtwork;
use App\Agovena\Theme\ThemeManager;
use App\Enums\PackageLifecycle;
use App\Enums\PackageSourceType;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

beforeEach(function (): void {
    $this->artworkDirectory = storage_path('framework/testing/package-artwork-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($this->artworkDirectory.'/resources/images');
    File::copy(public_path('vendor/agovena/logo.png'), $this->artworkDirectory.'/resources/images/logo.png');
});

afterEach(function (): void {
    File::deleteDirectory($this->artworkDirectory);
});

test('module and extension manifests retain optional local logo metadata without changing identity', function () {
    $module = ModuleManifest::fromArray([
        'id' => 'sample', 'name' => 'Sample', 'provider' => 'Example\\Sample',
        'logo' => 'resources/images/logo.png',
    ], $this->artworkDirectory);
    $extension = ExtensionManifest::fromArray([
        'id' => 'example', 'name' => 'Example', 'provider' => 'Example\\Extension',
        'category' => 'payment_gateway', 'logo' => 'resources/images/logo.png',
    ], $this->artworkDirectory);

    expect($module->id)->toBe('sample')
        ->and($extension->id)->toBe('example')
        ->and($module->logo)->toBe('resources/images/logo.png')
        ->and($extension->logo)->toBe('resources/images/logo.png')
        ->and(ModuleManifest::fromArray(['id' => 'old', 'name' => 'Old', 'provider' => 'Old\\Provider'], $this->artworkDirectory)->logo)->toBeNull();
});

test('package artwork resolver accepts only a real bounded local png', function () {
    $resolver = new PackageArtwork;
    $valid = $this->artworkDirectory.'/resources/images/logo.png';

    expect($resolver->resolve($this->artworkDirectory, 'resources/images/logo.png'))->toBe(realpath($valid));

    foreach ([null, '', '../logo.png', 'resources/images/../../logo.png',
        'resources/images/./logo.png', '/resources/images/logo.png',
        'https://example.test/logo.png', '//example.test/logo.png',
        'data:image/png;base64,AA==', 'resources\\images\\logo.png',
        'resources/images/missing.png', 'resources/images/logo.svg',
        'resources/images/logo.png?x=1', 'resources/images/logo.png#fragment',
    ] as $unsafe) {
        expect($resolver->resolve($this->artworkDirectory, $unsafe))->toBeNull();
    }

    File::put($this->artworkDirectory.'/resources/images/broken.png', '<script>alert(1)</script>');
    expect($resolver->resolve($this->artworkDirectory, 'resources/images/broken.png'))->toBeNull();
});

test('admin artwork endpoint serves only discovered package marks to authorized staff', function () {
    $package = $this->artworkDirectory.'/sample';
    File::ensureDirectoryExists($package.'/resources/images');
    File::copy(public_path('vendor/agovena/logo.png'), $package.'/resources/images/logo.png');
    File::put($package.'/module.json', json_encode([
        'id' => 'sample-artwork', 'name' => 'Sample Artwork',
        'provider' => 'Example\\Sample', 'logo' => 'resources/images/logo.png',
    ], JSON_THROW_ON_ERROR));
    config(['agovena.packages.extra_module_paths' => [$this->artworkDirectory]]);
    $url = route('admin.packages.artwork', ['kind' => 'module', 'id' => 'sample-artwork']);

    $this->get($url)->assertRedirect();
    $this->actingAs($this->createStaff(permissions: ['admin.access']))->get($url)->assertForbidden();
    $response = $this->actingAs($this->createStaff(permissions: ['admin.access', 'modules.view']))->get($url)->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('image/png');
    $this->get(route('admin.packages.artwork', ['kind' => 'module', 'id' => 'missing-artwork']))->assertNotFound();
});

test('shared package mark uses the secured image endpoint and falls back for unsafe marks', function () {
    $manifest = ModuleManifest::fromArray([
        'id' => 'sample', 'name' => 'Sample', 'provider' => 'Example\\Sample',
        'logo' => 'resources/images/logo.png',
    ], $this->artworkDirectory);
    $render = static fn (ModuleManifest $manifest): string => Blade::render(
        '<x-ag.package-mark :manifest="$manifest" kind="module" />',
        ['manifest' => $manifest],
    );

    expect($render($manifest))
        ->toContain('src="'.route('admin.packages.artwork', ['kind' => 'module', 'id' => 'sample']).'"')
        ->toContain('alt=""');

    $remote = Blade::render('<x-ag.package-mark :manifest="$manifest" kind="module" :on-disk="false" />', [
        'manifest' => $manifest,
    ]);
    expect($remote)->not->toContain('<img');

    $unsafe = ModuleManifest::fromArray([
        'id' => 'bad', 'name' => 'Bad', 'provider' => 'Example\\Bad',
        'logo' => 'https://example.test/remote.png',
    ], $this->artworkDirectory);
    expect($render($unsafe))->not->toContain('<img')->not->toContain('example.test');
});

test('module and extension cards display the shared mark without changing their actions', function () {
    $module = ModuleManifest::fromArray([
        'id' => 'sample', 'name' => 'Sample', 'provider' => 'Example\\Sample',
        'logo' => 'resources/images/logo.png',
    ], $this->artworkDirectory);
    $moduleRow = [
        'manifest' => $module, 'installed' => false, 'enabled' => false,
        'compatible' => true, 'on_disk' => true, 'lifecycle' => PackageLifecycle::Available,
    ];
    foreach (['available-module-row', 'preset-module-row'] as $partial) {
        $html = view('livewire.admin.packages.partials.'.$partial, [
            'row' => $moduleRow, 'presetId' => 'sample',
        ])->render();
        expect($html)->toContain('ag-package-mark')->toContain('Sample');
    }

    $extension = ExtensionManifest::fromArray([
        'id' => 'ext', 'name' => 'Extension', 'provider' => 'Example\\Extension',
        'category' => 'other', 'logo' => 'resources/images/logo.png',
    ], $this->artworkDirectory);
    $html = view('livewire.admin.packages.partials.extension-card', ['row' => [
        'manifest' => $extension, 'installed' => false, 'enabled' => false,
        'compatible' => true, 'lifecycle' => PackageLifecycle::Available,
        'source' => PackageSourceType::Path, 'can_purge' => false, 'on_disk' => true,
    ]])->render();
    expect($html)->toContain('ag-package-mark')->toContain('Extension');
});

test('theme preview endpoint accepts only an installed theme and its safe optional png', function () {
    $id = 'artwork-test-'.bin2hex(random_bytes(4));
    $dir = base_path('themes/'.$id);
    try {
        File::ensureDirectoryExists($dir);
        File::copy(public_path('vendor/agovena/logo.png'), $dir.'/preview.png');
        File::put($dir.'/theme.json', json_encode([
            'id' => $id, 'name' => 'Artwork Theme', 'preview' => 'preview.png',
        ], JSON_THROW_ON_ERROR));
        app()->forgetInstance(ThemeManager::class);
        $url = route('admin.appearance.themes.preview', ['id' => $id]);
        $theme = app(ThemeManager::class)->find($id);
        expect($theme)->not->toBeNull();
        expect(app(PackageArtwork::class)->resolve($theme->basePath, $theme->previewReference, true))->not->toBeNull();

        $this->get($url)->assertRedirect();
        $this->actingAs($this->createStaff(permissions: ['admin.access']))->get($url)->assertForbidden();
        $response = $this->actingAs($this->createStaff(permissions: ['admin.access', 'theme.view']))->get($url)->assertOk();
        expect($response->headers->get('Content-Type'))->toBe('image/png');
        $html = $this->get(route('admin.appearance.themes'))->assertOk()->getContent();
        expect($html)->toContain($url)->toContain('Artwork Theme');

        File::put($dir.'/preview.png', '<script>not an image</script>');
        $this->get($url)->assertNotFound();
        $this->get(route('admin.appearance.themes'))->assertOk()->assertDontSee('src="'.$url.'"', false);
    } finally {
        File::deleteDirectory($dir);
    }
});
