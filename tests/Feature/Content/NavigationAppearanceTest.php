<?php

declare(strict_types=1);

use App\Livewire\Admin\Content\NavigationIndex;
use App\Models\Menu;
use App\Models\MenuItem;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('known menu tabs lead in header order without dropping third-party handles', function (): void {
    $manager = $this->createStaff([], ['navigation.view', 'navigation.manage']);
    Menu::query()->create(['handle' => 'extra_links', 'name' => 'A Custom Menu']);

    $html = Livewire::actingAs($manager)->test(NavigationIndex::class)->html();
    preg_match_all('/wire:click="selectMenu\(\'([^\']+)\'\)"/', $html, $matches);
    $handles = $matches[1];

    expect($handles)->toBe(['header', 'footer', 'footer_legal', 'extra_links']);
});

test('the navigation view groups existing form controls and presents menu rows as responsive records', function (): void {
    $manager = $this->createStaff([], ['navigation.view', 'navigation.manage']);
    $menu = Menu::query()->create(['handle' => 'header', 'name' => 'Header']);
    $item = MenuItem::query()->create(['menu_id' => $menu->id, 'label' => 'Catalog', 'type' => 'url', 'url' => '/catalog', 'sort' => 1]);

    Livewire::actingAs($manager)->test(NavigationIndex::class)
        ->assertSee('c-navigation__tabs', false)
        ->assertSee('aria-current="true"', false)
        ->assertSee('c-navigation__layout', false)
        ->assertSee('c-navigation__form-panel', false)
        ->assertSee('c-navigation__structure-panel', false)
        ->assertSee('wire:submit="addItem"', false)
        ->assertSee('wire:model.live="type"', false)
        ->assertSee('wire:model="url"', false)
        ->assertSee('value="page"', false)
        ->assertSee('value="category"', false)
        ->assertSee('data-label="'.__('admin.content.navigation.label').'"', false)
        ->assertSee('data-label="'.__('admin.content.navigation.type').'"', false)
        ->assertSee('data-label="'.__('admin.content.navigation.target').'"', false)
        ->assertSee('wire:click="deleteItem('.$item->id.')"', false)
        ->assertSee('wire:confirm="'.__('admin.content.navigation.remove_confirm').'"', false)
        ->assertDontSee('wire:click="editItem', false)
        ->assertDontSee('wire:sortable', false);
});

test('mobile navigation records keep every field and the remove action in the visible card flow', function (): void {
    $path = resource_path('css/admin/screens/_navigation.css');
    expect(is_file($path))->toBeTrue();
    $css = file_get_contents($path);
    $entry = file_get_contents(resource_path('css/admin.css'));

    expect($entry)->toContain("@import './admin/screens/_navigation.css';")
        ->and($css)->toContain('@media (max-width: 720px)')
        ->toContain('.c-navigation__table tbody tr')
        ->toContain('.c-navigation__table tbody td::before')
        ->toContain('.c-navigation__actions')
        ->toContain('overflow-wrap: anywhere')
        ->not->toMatch('/#[0-9a-f]{3,8}\b/i');
});

test('read-only empty menus span exactly the three visible columns', function (): void {
    $viewer = $this->createStaff([], ['navigation.view']);

    Livewire::actingAs($viewer)->test(NavigationIndex::class)
        ->assertSee('colspan="3"', false)
        ->assertSee(__('admin.content.navigation.empty_title'));
});

test('view-only staff can read menu items but cannot see management controls', function (): void {
    $viewer = $this->createStaff([], ['navigation.view']);
    $menu = Menu::query()->create(['handle' => 'header', 'name' => 'Header']);
    $item = MenuItem::query()->create(['menu_id' => $menu->id, 'label' => 'Catalog', 'type' => 'url', 'url' => '/catalog', 'sort' => 1]);

    Livewire::actingAs($viewer)->test(NavigationIndex::class)
        ->assertSee('Catalog')
        ->assertSee('/catalog')
        ->assertDontSee('wire:submit="addItem"', false)
        ->assertDontSee('wire:click="deleteItem('.$item->id.')"', false);
});
