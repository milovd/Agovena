<?php

declare(strict_types=1);

test('mobile storefront navigation keeps preferences and styled links inside the drawer', function () {
    $html = $this->get(route('login'))
        ->assertOk()
        ->getContent();

    $drawerStart = strpos($html, 'id="store-mobile-nav"');
    $menuXPosition = strpos($html, 'class="store-header__menu-x"');
    $searchPosition = strpos($html, 'id="store-drawer-search"');
    $navPosition = strpos($html, 'class="store-drawer__nav"');

    expect($drawerStart)->toBeInt()
        ->and($menuXPosition)->toBeInt()
        ->and($searchPosition)->toBeInt()
        ->and($navPosition)->toBeInt()
        ->and($menuXPosition)->toBeLessThan($drawerStart)
        ->and($searchPosition)->toBeGreaterThan($drawerStart)
        ->and($navPosition)->toBeGreaterThan($searchPosition)
        ->and(substr_count($html, 'id="store-drawer-search"'))->toBe(1)
        ->and(substr_count($html, 'class="store-header__menu-x"'))->toBe(1)
        ->and(substr_count($html, 'class="store-drawer__close"'))->toBe(0)
        ->and(substr_count($html, 'store-header__search-wrap--mobile'))->toBe(0)
        ->and(substr_count($html, 'drawerTop: 0'))->toBe(1)
        ->and(substr_count($html, ":style=\"'top: ' + drawerTop + 'px'\""))->toBe(1)
        ->and(substr_count($html, "document.querySelector('.store-usp')"))->toBe(1)
        ->and(substr_count($html, 'ResizeObserver'))->toBeGreaterThanOrEqual(1)
        ->and(substr_count($html, 'store-drawer__prefs'))->toBe(1)
        ->and(substr_count($html, 'store-drawer__link-icon'))->toBeGreaterThan(0)
        ->and(substr_count($html, 'store-drawer__link-arrow'))->toBeGreaterThan(0)
        ->and(substr_count($html, '@scroll.window.passive="scheduleDrawerTopRefresh()"'))->toBe(1)
        ->and(substr_count($html, 'store-drawer-open'))->toBe(1);
});
