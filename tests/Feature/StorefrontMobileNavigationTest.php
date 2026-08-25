<?php

declare(strict_types=1);

use App\Models\Category;

test('mobile navigation keeps search in the header and cart in the drawer', function () {
    $rootCategory = Category::factory()->create([
        'name' => 'Mobile categories',
        'slug' => 'mobile-categories',
    ]);
    Category::factory()->create([
        'name' => 'Mobile child',
        'slug' => 'mobile-child',
        'parent_id' => $rootCategory->id,
    ]);

    $html = $this->get(route('login'))
        ->assertOk()
        ->getContent();

    $drawerStart = strpos($html, 'id="store-mobile-nav"');
    $menuXPosition = strpos($html, 'class="store-header__menu-x"');
    $searchPosition = strpos($html, 'id="store-mobile-header-search"');
    $navPosition = strpos($html, 'class="store-drawer__nav"');
    $drawerCartPosition = strpos(substr($html, $drawerStart ?: 0), '/cart');

    expect($drawerStart)->toBeInt()
        ->and($menuXPosition)->toBeInt()
        ->and($searchPosition)->toBeInt()
        ->and($navPosition)->toBeInt()
        ->and($menuXPosition)->toBeLessThan($drawerStart)
        ->and($searchPosition)->toBeLessThan($drawerStart)
        ->and($navPosition)->toBeGreaterThan($drawerStart)
        ->and($drawerCartPosition)->toBeInt()
        ->and(substr_count($html, 'id="store-mobile-header-search"'))->toBe(1)
        ->and(substr_count($html, 'id="store-drawer-search"'))->toBe(0)
        ->and(substr_count($html, 'class="store-header__menu-x"'))->toBe(1)
        ->and(substr_count($html, 'class="store-drawer__close"'))->toBe(0)
        ->and(substr_count($html, 'store-header__search-wrap--mobile'))->toBe(1)
        ->and(substr_count($html, 'M6 6h15l-1.5 9h-12z'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($html, 'store-drawer__link-icon'))->toBeGreaterThan(0)
        ->and(substr_count($html, 'drawerTop: 0'))->toBe(1)
        ->and(substr_count($html, ":style=\"'top: ' + drawerTop + 'px'\""))->toBe(1)
        ->and(substr_count($html, "document.querySelector('.store-usp')"))->toBe(1)
        ->and(substr_count($html, 'ResizeObserver'))->toBeGreaterThanOrEqual(1)
        ->and(substr_count($html, 'store-drawer__prefs'))->toBe(1)
        ->and(substr_count($html, 'mobileCatsOpen: false'))->toBe(1)
        ->and(substr_count($html, 'id="store-mobile-categories"'))->toBe(1)
        ->and(substr_count($html, 'store-drawer__category-root'))->toBeGreaterThan(0)
        ->and(substr_count($html, 'store-drawer__link-icon'))->toBeGreaterThan(0)
        ->and(substr_count($html, 'store-drawer__link-arrow'))->toBeGreaterThan(0)
        ->and(substr_count($html, '@scroll.window.passive="scheduleDrawerTopRefresh()"'))->toBe(1)
        ->and(substr_count($html, 'store-drawer-open'))->toBe(1);
});
