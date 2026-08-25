<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;

test('mobile navigation keeps search and cart in the header with a collapsed account drawer', function () {
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
    $drawerEnd = strpos($html, '</header>', $drawerStart ?: 0);
    $headerMarkup = substr($html, 0, $drawerStart ?: 0);
    $drawerMarkup = substr($html, $drawerStart ?: 0, ($drawerEnd ?: 0) - ($drawerStart ?: 0));
    $menuXPosition = strpos($html, 'class="store-header__menu-x"');
    $searchPosition = strpos($html, 'id="store-mobile-header-search"');
    $navPosition = strpos($html, 'class="store-drawer__nav"');
    $headerCartPosition = strpos($headerMarkup, 'store-header__cart');
    $drawerCartPosition = strpos($drawerMarkup, '/cart');

    expect($drawerStart)->toBeInt()
        ->and($menuXPosition)->toBeInt()
        ->and($searchPosition)->toBeInt()
        ->and($navPosition)->toBeInt()
        ->and($headerCartPosition)->toBeInt()
        ->and($menuXPosition)->toBeLessThan($drawerStart)
        ->and($searchPosition)->toBeLessThan($drawerStart)
        ->and($navPosition)->toBeGreaterThan($drawerStart)
        ->and($drawerCartPosition)->toBeFalse()
        ->and(substr_count($html, 'id="store-mobile-header-search"'))->toBe(1)
        ->and(substr_count($html, 'id="store-drawer-search"'))->toBe(0)
        ->and(substr_count($html, 'class="store-header__menu-x"'))->toBe(1)
        ->and(substr_count($html, 'class="store-drawer__close"'))->toBe(0)
        ->and(substr_count($html, 'store-header__search-wrap--mobile'))->toBe(1)
        ->and(substr_count($html, 'M6 6h15l-1.5 9h-12z'))->toBe(1)
        ->and(substr_count($html, 'drawerTop: 0'))->toBe(1)
        ->and(substr_count($html, ":style=\"'top: ' + drawerTop + 'px'\""))->toBe(1)
        ->and(substr_count($html, 'x-bind:hidden="!navOpen"'))->toBe(1)
        ->and(substr_count($html, "document.querySelector('.store-usp')"))->toBe(1)
        ->and(substr_count($html, 'ResizeObserver'))->toBeGreaterThanOrEqual(1)
        ->and(substr_count($html, 'store-drawer__prefs'))->toBe(1)
        ->and(substr_count($html, 'mobileCatsOpen: false'))->toBe(1)
        ->and(substr_count($html, 'mobileCategoryOpen: null'))->toBe(1)
        ->and(substr_count($html, 'store-drawer__category-row'))->toBeGreaterThan(0)
        ->and(substr_count($html, ":class=\"{ 'is-open': mobileCategoryOpen ==="))->toBeGreaterThan(0)
        ->and(substr_count($html, 'x-show="mobileCategoryOpen ==='))->toBeGreaterThan(0)
        ->and(substr_count($html, 'x-bind:hidden="mobileCategoryOpen !=='))->toBeGreaterThan(0)
        ->and(substr_count($html, 'mobileAccountOpen: false'))->toBe(1)
        ->and(substr_count($html, 'id="store-mobile-categories"'))->toBe(1)
        ->and(substr_count($html, 'store-drawer__category-root'))->toBeGreaterThan(0)
        ->and(substr_count($html, '@scroll.window.passive="scheduleDrawerTopRefresh()"'))->toBe(1)
        ->and(substr_count($html, 'class="store-drawer__auth"'))->toBe(1)
        ->and(substr_count($html, 'store-drawer__account-toggle'))->toBe(0)
        ->and(substr_count($html, 'store-drawer-open'))->toBe(1);
});

test('authenticated mobile navigation keeps the account dropdown at the bottom', function () {
    $user = User::factory()->create([
        'name' => 'Casey Customer',
    ]);

    $html = $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->getContent();

    $drawerStart = strpos($html, 'id="store-mobile-nav"');
    $drawerEnd = strpos($html, '</header>', $drawerStart ?: 0);
    $drawerMarkup = substr($html, $drawerStart ?: 0, ($drawerEnd ?: 0) - ($drawerStart ?: 0));

    expect(substr_count($drawerMarkup, 'store-drawer__account-toggle'))->toBe(1)
        ->and(substr_count($drawerMarkup, 'store-drawer__account-icon'))->toBe(1)
        ->and(substr_count($drawerMarkup, 'store-drawer__account-name'))->toBe(1)
        ->and(substr_count($drawerMarkup, 'Casey Customer'))->toBe(1)
        ->and(substr_count($drawerMarkup, 'id="store-mobile-account"'))->toBe(1)
        ->and(substr_count($drawerMarkup, 'class="store-drawer__auth"'))->toBe(0);
});
