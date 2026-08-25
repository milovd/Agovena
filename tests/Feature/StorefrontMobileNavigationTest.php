<?php

declare(strict_types=1);

test('mobile storefront navigation keeps close and search above the drawer nav', function () {
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
        ->and(substr_count($html, 'store-header__search-wrap--mobile'))->toBe(0);
});
