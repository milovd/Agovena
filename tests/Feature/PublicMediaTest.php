<?php

declare(strict_types=1);

use App\Agovena\Media\PublicMedia;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

test('public media urls are origin relative and omit missing files', function () {
    config(['app.url' => 'http://cdn.example.test']);
    Storage::fake('public');
    Storage::disk('public')->put('demo/hero-promo.jpg', 'img-bytes');

    expect(PublicMedia::url('demo/hero-promo.jpg'))->toBe('/storage/demo/hero-promo.jpg')
        ->and(PublicMedia::url('products/missing.png'))->toBeNull()
        ->and(PublicMedia::url('../secrets.txt'))->toBeNull()
        ->and(PublicMedia::url(''))->toBeNull();
});

test('public storage route serves existing files and rejects traversal', function () {
    Storage::fake('public');
    Storage::disk('public')->put('demo/hero-promo.jpg', 'img-bytes');

    $this->get('/storage/demo/hero-promo.jpg')
        ->assertOk();

    expect($this->get('/storage/demo/hero-promo.jpg')->streamedContent())->toBe('img-bytes');

    $this->get('/storage/missing.jpg')->assertNotFound();
    $this->get('/storage/%2e%2e/secrets.txt')->assertNotFound();
});

test('homepage hero omits img tags when storage files are missing', function () {
    Storage::fake('public');
    Product::factory()->active()->create([
        'name' => 'Ghost Phone',
        'image_path' => 'products/missing-hero.png',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('storage/products/missing-hero.png', false)
        ->assertDontSee('storage/demo/hero-promo.jpg', false);
});

test('private local-disk files are not reachable at /storage', function () {
    Storage::fake('public');
    Storage::fake('local');
    Storage::disk('local')->put('invoices/secret.pdf', 'confidential');
    Storage::disk('local')->put('digital/secret.zip', 'zip-bytes');
    Storage::disk('public')->put('branding/logo.png', 'public-bytes');

    expect(config('filesystems.disks.local.serve'))->toBeFalse()
        ->and(config('filesystems.disks.public.serve'))->toBeTrue()
        ->and(config('filesystems.disks.public.url'))->toBe('/storage');

    $this->get('/storage/invoices/secret.pdf')->assertNotFound();
    $this->get('/storage/digital/secret.zip')->assertNotFound();
    $this->get('/storage/branding/logo.png')->assertOk();
    expect($this->get('/storage/branding/logo.png')->streamedContent())->toBe('public-bytes');
});

test('storefront html does not embed APP_URL into public media paths', function () {
    config(['app.url' => 'http://127.0.0.1:8000']);
    Storage::fake('public');
    Storage::disk('public')->put('demo/hero-promo.jpg', 'img-bytes');

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('/storage/demo/hero-promo.jpg')
        ->and($html)->not->toContain('http://127.0.0.1:8000/storage/')
        ->and($html)->not->toContain('http://localhost/storage/');
});

test('homepage hero uses origin-relative storage urls when files exist', function () {
    Storage::fake('public');
    Storage::disk('public')->put('demo/hero-promo.jpg', 'img-bytes');
    Storage::disk('public')->put('products/ghost.png', 'img-bytes');
    Product::factory()->active()->create([
        'name' => 'Ghost Phone',
        'image_path' => 'products/ghost.png',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('/storage/demo/hero-promo.jpg', false)
        ->assertSee('/storage/products/ghost.png', false)
        ->assertDontSee('cdn.example.test/storage', false)
        ->assertDontSee(rtrim((string) config('app.url'), '/').'/storage/', false);
});
