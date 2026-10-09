<?php

use App\Agovena\Catalog\ListStorefrontProducts;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Media\ProductMedia;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use App\Livewire\Admin\Appearance\Customize;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('theme manager discovers default theme and config defaults', function () {
    $themes = app(ThemeManager::class);
    $theme = $themes->active();

    expect($theme->id)->toBe('default')
        ->and($themes->all())->toHaveKey('default');

    $config = $themes->config();
    expect($config->bool('header.announcement_enabled'))->toBeTrue()
        ->and($config->string('header.custom_nav_items'))->toBe('3')
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
        ->assertSee('x-data="storefrontHero"', false)
        ->assertDontSee('x-data="{ ready: false }"', false)
        ->assertSee('Categories', false)
        ->assertSee('store-cats', false)
        ->assertSee('Search products', false)
        ->assertSee('DM+Sans', false);
});

test('homepage keeps the storefront chrome compact and the hero deterministic', function () {
    expect(Artisan::call('agovena:seed-demo', ['--force' => true, '--skip-accounts' => true]))->toBe(0);
    $minecraft = Product::query()->where('slug', 'minecraft-survival-server')->firstOrFail();
    $publicDisk = Storage::disk('public');
    expect($minecraft->image_path)->toMatch('/^demo\/minecraft-survival-server-[a-f0-9]{12}\.jpg$/')
        ->and($publicDisk->exists($minecraft->image_path))->toBeTrue()
        ->and(ProductMedia::primaryUrl($minecraft))->toBe('/storage/'.$minecraft->image_path);

    $teeImage = Product::query()->where('slug', 'agovena-essential-tee')->firstOrFail()->image_path;
    $domainImage = Product::query()->where('slug', 'domain-registration-and-dns-management')->firstOrFail()->image_path;
    $pythonImage = Product::query()->where('slug', 'python-automation-starter-kit')->firstOrFail()->image_path;

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->not->toContain('class="store-nav__link" href="/categories/events"')
        ->not->toContain('store-drawer__primary-link" href="/categories/events"')
        ->toContain('loading="eager"')
        ->toContain('decoding="sync"')
        ->toContain('fetchpriority="high"')
        ->toContain('store-brand__fallback')
        ->toContain('Order physical goods, digital products and services together, with one checkout.');

    expect(app(ThemeManager::class)->config()->sections()[0])->not->toHaveKey('image');
    expect(substr_count($html, 'class="store-hero__plate store-hero__plate--'))->toBe(4);
    expect($html)
        ->toContain('/storage/'.$minecraft->image_path)
        ->toContain('/storage/'.$domainImage)
        ->toContain('/storage/'.$teeImage)
        ->toContain('/storage/'.$pythonImage)
        ->toContain('Products')
        ->toContain('About Us')
        ->not->toContain('>Services</a>')
        ->not->toContain('>Domains</a>')
        ->not->toContain('>Deals</a>')
        ->not->toContain('href="/domains"')
        ->toContain('store-promo__media')
        ->not->toContain('store-promo__placeholder')
        ->not->toContain('demo/promo-split.jpg')
        ->not->toContain('store-nav__more')
        ->not->toContain('>More<')
        ->not->toContain('Agovena Essential Tee');
});

test('custom navigation setting controls desktop items without an overflow menu', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true, '--skip-accounts' => true]);
    $config = app(ThemeManager::class)->config();

    $config->set('header.custom_nav_items', '2');
    $limitedHtml = $this->get('/')->assertOk()->getContent();
    preg_match('/<nav class="store-nav"[^>]*>(.*?)<\/nav>/s', $limitedHtml, $limitedMatch);

    expect($limitedMatch[1] ?? '')->toContain('>Products</a>')
        ->toContain('>About Us</a>')
        ->not->toContain('>Services</a>')
        ->not->toContain('>Domains</a>')
        ->not->toContain('>Deals</a>')
        ->not->toContain('store-nav__more');
    expect($limitedHtml)->toContain('store-drawer__primary-link')->toContain('Products');

    $config->set('header.custom_nav_items', 'infinite');
    $infiniteHtml = $this->get('/')->assertOk()->getContent();
    preg_match('/<nav class="store-nav"[^>]*>(.*?)<\/nav>/s', $infiniteHtml, $infiniteMatch);

    expect($infiniteMatch[1] ?? '')->toContain('>About Us</a>')
        ->not->toContain('>Services</a>')
        ->not->toContain('>Domains</a>')
        ->not->toContain('>Deals</a>')
        ->not->toContain('store-nav__more');
});

test('demo seeder populates catalog and refuses production', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true, '--skip-accounts' => true]);

    expect(Product::query()->count())->toBeGreaterThan(5)
        ->and(Category::query()->whereNull('parent_id')->count())->toBe(5)
        ->and(Category::query()->whereNotNull('parent_id')->count())->toBe(2)
        ->and(Page::query()->published()->count())->toBeGreaterThan(0);

    $featured = app(ListStorefrontProducts::class)->handle(limit: 8);
    expect($featured)->not->toBeEmpty();

    $this->get('/')->assertOk()->assertSee($featured->first()->name, false);
    $this->get('/categories/provisioning')->assertOk();
    $this->get('/categories/game-hosting')->assertOk();
    $this->get('/about')->assertOk()->assertSee('About', false);
});

test('categories index page lists root categories', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true, '--skip-accounts' => true]);

    $this->get('/categories')
        ->assertOk()
        ->assertSee('Provisioning', false)
        ->assertSee('Physical Products', false);
});

test('product detail shows gallery nav and zero reviews', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true, '--skip-accounts' => true]);
    $pterodactyl = app(ExtensionSettingsRepository::class);
    $pterodactyl->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    $pterodactyl->set('pterodactyl', 'application_api_key', '[REDACTED]', secret: true);
    $pterodactyl->set('pterodactyl', 'user_id', '1');
    $heroImage = Product::query()->where('slug', 'minecraft-survival-server')->firstOrFail()->image_path;

    $this->get('/products/minecraft-survival-server')
        ->assertOk()
        ->assertSee('View 0 reviews', false)
        ->assertSee('class="store-product__gallery"', false)
        ->assertSee('/storage/'.$heroImage, false)
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('loading="eager"', false)
        ->assertSee('class="store-product__quantity-row"', false)
        ->assertSee('class="visually-hidden" for="quantity"', false)
        ->assertDontSee('class="store-product__quantity-label"', false)
        ->assertSeeInOrder(['class="store-product__rating"', 'class="store-product__lede"', 'class="store-product__price-row"', 'class="store-product__quantity-row"', 'class="store-product__actions"'], false)
        ->assertSee('x-data="storefrontProductGallery"', false)
        ->assertSee('x-data="storefrontProductPanels"', false)
        ->assertSee('Details', false)
        ->assertSee('Reviews', false)
        ->assertSee('Specifications', false)
        ->assertSee('8 GB', false)
        ->assertSee('No reviews yet', false)
        ->assertSee('A ready-to-configure Minecraft service plan for the Pterodactyl demo flow.', false);
});

test('store setting can disable reviews on product pages', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true, '--skip-accounts' => true]);
    app(SettingsRepository::class)->set('store', 'enable_reviews', false);

    $this->get('/products/minecraft-survival-server')
        ->assertOk()
        ->assertDontSee('View 0 reviews', false)
        ->assertDontSee('No reviews yet', false)
        ->assertSee('Details', false);
});

test('search suggest returns product thumbnails', function () {
    Artisan::call('agovena:seed-demo', ['--force' => true, '--skip-accounts' => true]);

    $this->getJson(route('storefront.search.suggest', ['q' => 'Minecraft']))
        ->assertOk()
        ->assertJsonPath('items.0.name', 'Minecraft Survival Server')
        ->assertJsonStructure(['query', 'items' => [['name', 'slug', 'url', 'price', 'image']], 'results_url']);
});

test('theme customize saves accent and hero title', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/appearance/customize')
        ->assertOk()
        ->assertSee('theme-customizer__workspace', false)
        ->assertSee('theme-customizer__tabs', false)
        ->assertSee('ag-product-tabs', false)
        ->assertSee('theme-customizer__save-bar', false)
        ->assertDontSee('theme-customizer__identity', false)
        ->assertDontSee('style="margin-top: 1rem;"', false);

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

test('theme customize exposes shared tabs and both color mode palettes', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/appearance/customize')
        ->assertOk()
        ->assertSee('ag-product-tabs', false)
        ->assertSee(__('admin.appearance.customize.tabs.design'), false)
        ->assertSee(__('admin.appearance.customize.tabs.homepage'), false)
        ->assertSee(__('admin.appearance.theme_fields.colors.dark_surface'), false)
        ->assertSee(__('admin.appearance.theme_fields.appearance.default_color_mode'), false)
        ->assertDontSee('theme-customizer__navigation', false);

    Livewire\Livewire::actingAs($staff)
        ->test(Customize::class)
        ->set('values.appearance.default_color_mode', 'dark')
        ->set('values.colors.dark_surface', '#101827')
        ->set('values.colors.dark_accent', '#93C5FD')
        ->call('save')
        ->assertHasNoErrors();

    $config = app(ThemeManager::class)->config();
    expect($config->string('appearance.default_color_mode'))->toBe('dark')
        ->and($config->string('colors.dark_surface'))->toBe('#101827')
        ->and($config->string('colors.dark_accent'))->toBe('#93C5FD');
});

test('homepage editor exposes persisted section fields and reloads edited values without losing siblings', function () {
    $staff = $this->createStaff();
    $config = app(ThemeManager::class)->config();
    $sections = $config->sections();

    $editor = Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->set('tab', 'homepage')
        ->assertSee('wire:model="sections.0.eyebrow"', false)
        ->assertSee('wire:model="sections.0.cta_href"', false)
        ->assertSee('wire:model="sections.1.lede"', false)
        ->assertSee('wire:model="sections.2.lede"', false)
        ->assertSee('wire:model="sections.3.cta_href"', false)
        ->assertSet('sections.0.eyebrow', $sections[0]['eyebrow'])
        ->assertSet('sections.3.cta_href', $sections[3]['cta_href']);

    $editor->set('sections.0.eyebrow', 'A new introduction')
        ->set('sections.0.cta_href', '/featured')
        ->set('sections.1.lede', 'Category introduction')
        ->set('sections.2.lede', 'Product introduction')
        ->set('sections.3.cta_href', 'javascript:alert(1)')
        ->call('save')
        ->assertHasNoErrors();

    $saved = $config->sections();
    expect($saved[0]['eyebrow'])->toBe('A new introduction')
        ->and($saved[0]['cta_href'])->toBe('/featured')
        ->and($saved[0]['title'])->toBe($sections[0]['title'])
        ->and($saved[1]['lede'])->toBe('Category introduction')
        ->and($saved[2]['lede'])->toBe('Product introduction')
        ->and($saved[3]['cta_href'])->toBe('')
        ->and($saved[3]['title'])->toBe($sections[3]['title']);

    Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->assertSet('sections.0.eyebrow', 'A new introduction')
        ->assertSet('sections.0.cta_href', '/featured')
        ->assertSet('sections.1.lede', 'Category introduction')
        ->assertSet('sections.2.lede', 'Product introduction')
        ->assertSet('sections.3.cta_href', '');
});

test('trust strip edits stored items rather than an ignored section title', function () {
    $staff = $this->createStaff();
    $config = app(ThemeManager::class)->config();
    $original = $config->sections()[4]['items'];

    $editor = Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->set('tab', 'homepage')
        ->assertSee('wire:model="sections.4.items.0.title"', false)
        ->assertSee('wire:model="sections.4.items.0.text"', false)
        ->assertSee('wire:click="addTrustItem(4)"', false)
        ->assertDontSee('wire:model="sections.4.title"', false)
        ->assertSet('sections.4.items.0.title', $original[0]['title']);

    $editor->set('sections.4.items.0.title', '<b>Protected</b> checkout')
        ->set('sections.4.items.0.text', 'Clear terms')
        ->call('addTrustItem', 4)
        ->set('sections.4.items.4.title', 'New benefit')
        ->set('sections.4.items.4.text', 'New detail')
        ->call('removeTrustItem', 4, 1)
        ->call('save')
        ->assertHasNoErrors();

    $items = $config->sections()[4]['items'];
    expect($items)->toHaveCount(4)
        ->and($items[0])->toBe(['title' => 'Protected checkout', 'text' => 'Clear terms'])
        ->and($items[1])->toBe($original[2])
        ->and($items[3])->toBe(['title' => 'New benefit', 'text' => 'New detail']);

    Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->assertSet('sections.4.items', $items);
    $this->get('/')->assertOk()
        ->assertSeeText('Protected checkout')
        ->assertSeeText('New detail')
        ->assertDontSee('<b>Protected</b>', false);
});

test('header owns USP editing without duplicating its controls on homepage', function () {
    $staff = $this->createStaff();
    $config = app(ThemeManager::class)->config();
    $before = $config->uspItems();

    Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->set('tab', 'header')
        ->assertSee('wire:model="uspItems.0.text"', false)
        ->assertSee('wire:click="addUspItem"', false)
        ->set('uspItems.0.text', 'Header benefit')
        ->call('save')
        ->assertHasNoErrors();

    expect($config->uspItems()[0]['text'])->toBe('Header benefit')
        ->and($config->uspItems()[1])->toBe($before[1]);

    Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->set('tab', 'header')
        ->assertSet('uspItems.0.text', 'Header benefit');
    Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->set('tab', 'homepage')
        ->assertDontSee('wire:model="uspItems.0.text"', false)
        ->assertSee('wire:model="sections.4.items.0.title"', false);
});

test('homepage retains every stored section field when saving from Header', function () {
    $staff = $this->createStaff();
    $config = app(ThemeManager::class)->config();
    $sections = [
        ['type' => 'hero', 'eyebrow' => 'Existing eyebrow', 'title' => 'Existing hero', 'lede' => 'Existing intro', 'cta_label' => 'Enter', 'cta_href' => '/shop'],
        ['type' => 'featured_products', 'title' => 'Existing products', 'lede' => 'Product copy', 'limit' => 12],
        ['type' => 'featured_categories', 'title' => 'Existing categories', 'lede' => 'Category copy'],
        ['type' => 'promo_split', 'title' => 'Existing promo', 'body' => 'Promo copy', 'cta_label' => 'Learn', 'cta_href' => 'https://example.test/promo', 'image' => 'demo/promo.png'],
        ['type' => 'trust_strip', 'items' => [['title' => 'Existing trust', 'text' => 'Trust detail']]],
        ['type' => 'rich_text', 'title' => 'Existing text', 'body' => 'Body copy'],
    ];
    $config->set('homepage.sections', $sections);

    Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->assertSet('sections', $sections)
        ->set('tab', 'header')
        ->call('save')
        ->assertHasNoErrors();

    expect($config->sections())->toBe($sections);
    Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->set('tab', 'homepage')
        ->assertSet('sections', $sections)
        ->assertSee('wire:model="sections.5.body"', false);
});

test('trust strip add action respects the six item limit and section boundary', function () {
    $staff = $this->createStaff();
    $editor = Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->call('addTrustItem', 0)
        ->assertSet('sections.0.items', null)
        ->call('removeTrustItem', 0, 0)
        ->assertCount('sections.4.items', 4)
        ->call('addTrustItem', 4)
        ->call('addTrustItem', 4)
        ->assertCount('sections.4.items', 6)
        ->assertDontSee('wire:click="addTrustItem(4)"', false)
        ->call('addTrustItem', 4)
        ->assertCount('sections.4.items', 6);
});

test('trust strip actions ignore malformed client item payloads', function () {
    $staff = $this->createStaff();
    Livewire\Livewire::actingAs($staff)->test(Customize::class)
        ->set('tab', 'homepage')
        ->set('sections.4.items', 'invalid')
        ->call('addTrustItem', 4)
        ->call('removeTrustItem', 4, 0)
        ->assertSet('sections.4.items', 'invalid');
});

test('theme customize normalizes section content and link destinations', function () {
    $config = app(ThemeManager::class)->config();
    $config->set('homepage.sections', [
        ['type' => 'unknown', 'title' => 'Ignored'],
        ['type' => 'hero', 'title' => '<script>alert(1)</script>Safe', 'cta_href' => 'javascript:alert(1)', 'image' => '../secret.svg'],
        ['type' => 'featured_products', 'limit' => 999],
    ]);

    expect($config->sections())->toHaveCount(2)
        ->and($config->sections()[0]['title'])->toBe('alert(1)Safe')
        ->and($config->sections()[0]['cta_href'])->toBe('')
        ->and($config->sections()[0])->not->toHaveKey('image')
        ->and($config->sections()[1]['limit'])->toBe(24);
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
