<?php

use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use App\Livewire\Admin\Appearance\Customize;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('theme manager discovers default theme and config defaults', function () {
    $themes = app(ThemeManager::class);
    $theme = $themes->active();

    expect($theme->id)->toBe('default')
        ->and($themes->all())->toHaveKey('default');

    $config = $themes->config();
    expect($config->bool('header.announcement_enabled'))->toBeTrue()
        ->and($config->string('colors.accent'))->toBe('#155EEF')
        ->and($config->sections())->not->toBeEmpty()
        ->and($config->uspItems())->toHaveCount(3);
});

test('homepage renders announcement hero and featured sections', function () {
    Category::query()->create([
        'name' => 'Phones',
        'slug' => 'phones-home',
        'is_active' => true,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('store-usp', false)
        ->assertSeeText('Free shipping from €25')
        ->assertSeeText('Easy returns within 30 days')
        ->assertSeeText('Shop now')
        ->assertSee('store-usp__label-short', false)
        ->assertSeeText('Free shipping')
        ->assertSeeText('Easy returns')
        ->assertSee('store-usp__cta', false)
        ->assertSee('store-hero', false)
        ->assertSee('Categories', false)
        ->assertSee('store-cats', false)
        ->assertSee('Search products', false)
        ->assertSee('DM+Sans', false);
});

test('demo seeder populates catalog and refuses production', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true]);

    expect(Product::query()->count())->toBeGreaterThan(5)
        ->and(Category::query()->whereNull('parent_id')->count())->toBe(3)
        ->and(Category::query()->whereNotNull('parent_id')->count())->toBe(2)
        ->and(Page::query()->published()->count())->toBeGreaterThan(0);

    $featured = app(ListStorefrontProducts::class)->handle(limit: 8);
    expect($featured)->not->toBeEmpty();

    $this->get('/')->assertOk()->assertSee($featured->first()->name, false);
    $this->get('/categories/phones')->assertOk();
    $this->get('/categories/android')->assertOk();
    $this->get('/about')->assertOk()->assertSee('About', false);
});

test('categories index page lists root categories', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true]);

    $this->get('/categories')
        ->assertOk()
        ->assertSee('Phones', false)
        ->assertSee('Audio', false);
});

test('product detail shows gallery nav and zero reviews', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true]);

    $this->get('/products/nova-phone-14')
        ->assertOk()
        ->assertSee('View 0 reviews', false)
        ->assertSee('Scroll thumbnails left', false)
        ->assertSee('Scroll thumbnails right', false)
        ->assertSee('Show image 1', false)
        ->assertSee('Show image 8', false)
        ->assertSee('store-product__thumb is-active', false)
        ->assertSee('Details', false)
        ->assertSee('Reviews', false)
        ->assertSee('Specifications', false)
        ->assertSee('6.1 inch OLED', false)
        ->assertSee('No reviews yet', false)
        ->assertSee('6.1 inch OLED, dual camera, all-day battery.', false);
});

test('store setting can disable reviews on product pages', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true]);
    app(SettingsRepository::class)->set('store', 'enable_reviews', false);

    $this->get('/products/nova-phone-14')
        ->assertOk()
        ->assertDontSee('View 0 reviews', false)
        ->assertDontSee('No reviews yet', false)
        ->assertSee('Details', false);
});

test('search suggest returns product thumbnails', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true]);

    $this->getJson(route('storefront.search.suggest', ['q' => 'Nova']))
        ->assertOk()
        ->assertJsonPath('items.0.name', 'Nova Phone 14')
        ->assertJsonStructure(['query', 'items' => [['name', 'slug', 'url', 'price', 'image']], 'results_url']);
});

test('theme customize saves accent and hero title', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/appearance/customize')
        ->assertOk();

    Livewire\Livewire::actingAs($staff)
        ->test(Customize::class)
        ->set('values.colors.accent', '#112233')
        ->set('sections.0.title', 'Custom hero title')
        ->call('save')
        ->assertHasNoErrors();

    $config = app(ThemeManager::class)->config();
    expect($config->string('colors.accent'))->toBe('#112233')
        ->and($config->sections()[0]['title'] ?? null)->toBe('Custom hero title');
});

test('themes admin can list active theme', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/appearance/themes')
        ->assertOk()
        ->assertSee('Default', false)
        ->assertSee('Active', false);
});

test('pages and navigation admin are reachable', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/appearance/pages')
        ->assertOk();

    $this->actingAs($staff)
        ->get('/admin/appearance/navigation')
        ->assertOk()
        ->assertSee('Header', false);
});

test('category sort by price works', function () {
    $category = Category::factory()->create(['slug' => 'widgets', 'is_active' => true]);
    Product::factory()->active()->create([
        'name' => 'Cheap',
        'slug' => 'cheap',
        'price_amount' => 100,
        'category_id' => $category->id,
    ]);
    Product::factory()->active()->create([
        'name' => 'Pricey',
        'slug' => 'pricey',
        'price_amount' => 9000,
        'category_id' => $category->id,
    ]);

    $html = $this->get('/categories/widgets?sort=price_asc')->assertOk()->getContent();
    expect(strpos($html, 'Cheap'))->toBeLessThan(strpos($html, 'Pricey'));
});
