<?php

declare(strict_types=1);

use App\Agovena\Content\MenuResolver;
use App\Livewire\Admin\Content\NavigationIndex;
use App\Models\Menu;
use App\Models\MenuItem;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('navigation rejects menu links with an unsafe scheme', function (string $url) {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(NavigationIndex::class)
        ->set('label', 'Unsafe')
        ->set('type', 'url')
        ->set('url', $url)
        ->call('addItem')
        ->assertHasErrors(['url'])
        ->assertSee(__('admin.content.navigation.url_unsafe'));

    expect(MenuItem::query()->where('label', 'Unsafe')->exists())->toBeFalse();
})->with([
    'javascript' => 'javascript:alert(1)',
    'mixed case javascript' => 'JaVaScRiPt:alert(1)',
    'leading whitespace' => '  javascript:alert(1)',
    'data url' => 'data:text/html;base64,PHNjcmlwdD4=',
    'vbscript' => 'vbscript:msgbox(1)',
    'protocol relative' => '//evil.example',
    'control characters' => "/path\nmore",
]);

test('navigation accepts internal, anchor, web, mail and phone links', function (string $url) {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(NavigationIndex::class)
        ->set('label', 'Safe')
        ->set('type', 'url')
        ->set('url', $url)
        ->call('addItem')
        ->assertHasNoErrors();

    expect(MenuItem::query()->where('label', 'Safe')->value('url'))->toBe($url);
})->with([
    'root relative' => '/pages/about',
    'anchor' => '#catalog',
    'https' => 'https://example.com/docs',
    'http' => 'http://example.com',
    'mail' => 'mailto:hello@example.com',
    'phone' => 'tel:+3212345678',
]);

test('storefront menus never render a stored unsafe link', function () {
    $menu = Menu::query()->firstOrCreate(['handle' => 'header'], ['name' => 'Header']);
    MenuItem::query()->create([
        'menu_id' => $menu->id,
        'label' => 'Legacy unsafe',
        'type' => 'url',
        'url' => 'javascript:alert(document.cookie)',
        'sort' => 1,
    ]);

    $links = collect(app(MenuResolver::class)->handle('header'))->keyBy('label');

    expect($links->has('Legacy unsafe'))->toBeTrue()
        ->and($links->get('Legacy unsafe')['url'])->toBeNull();
});
