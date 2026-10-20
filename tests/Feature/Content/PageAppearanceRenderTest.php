<?php

declare(strict_types=1);

use App\Models\Page;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('pages list presents real rows with a responsive identity, publication state and existing actions', function (): void {
    $manager = $this->createStaff([], ['pages.view', 'pages.manage']);
    $published = Page::query()->create(['title' => 'Our story', 'slug' => 'our-story', 'status' => 'published']);
    $draft = Page::query()->create(['title' => 'Private notes', 'slug' => 'private-notes', 'status' => 'draft']);

    $this->actingAs($manager)->get(route('admin.appearance.pages'))
        ->assertOk()
        ->assertSee('admin-page--pages', false)
        ->assertSee('ag-page-list__table', false)
        ->assertSee('ag-page-list__identity', false)
        ->assertSee('ag-page-list__row--selectable', false)
        ->assertSee('ag-badge--success', false)
        ->assertSee('ag-badge--muted', false)
        ->assertSee('wire:model.live.debounce.300ms="search"', false)
        ->assertSee('wire:model.live="publicationFilter"', false)
        ->assertSee('@click="toggleAll"', false)
        ->assertSee("wire:click=\"bulkSetStatus('published')\"", false)
        ->assertSee('wire:click="deletePage('.$draft->id.')"', false)
        ->assertSee(route('admin.appearance.pages.edit', $draft), false)
        ->assertSee(route('storefront.page', ['slug' => $published->slug]), false)
        ->assertSee('aria-controls="page-actions-'.$published->id.'"', false)
        ->assertSee('aria-controls="page-actions-'.$draft->id.'"', false)
        ->assertSee('Private notes')
        ->assertSee('/private-notes');

    $markup = $this->actingAs($manager)->get(route('admin.appearance.pages'))->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($markup);
    $xpath = new DOMXPath($dom);
    foreach ([$published, $draft] as $page) {
        $row = $xpath->query('//tr[@*[name()="wire:key"]="page-'.$page->id.'"]')->item(0);
        expect($row)->not->toBeNull();
        $actions = $xpath->query('.//td[contains(@class, "ag-page-list__actions")]', $row)->item(0);
        expect($xpath->query('.//a[contains(@class, "ag-icon-btn")]', $actions))->toHaveCount(1)
            ->and($xpath->query('.//div[@id="page-actions-'.$page->id.'"]//button[@*[name()="wire:confirm"]]', $actions))->toHaveCount(1);
    }
});

test('page-row action menus are not clipped by their table wrapper', function (): void {
    $css = file_get_contents(resource_path('css/admin/screens/_pages.css'));
    expect($css)->toMatch('/\\.ag-page-list__wrap\\s*\\{[^}]*overflow:\\s*visible;[^}]*contain:\\s*none;/s')
        ->and($css)->toContain('tr:nth-last-child(-n + 2):not(:first-child) .ag-menu__panel');
});

test('view-only staff retain the responsive page list without management controls', function (): void {
    $viewer = $this->createStaff([], ['pages.view']);
    $draft = Page::query()->create(['title' => 'Reader copy', 'slug' => 'reader-copy', 'status' => 'draft']);

    $this->actingAs($viewer)->get(route('admin.appearance.pages'))
        ->assertOk()
        ->assertSee('ag-page-list__identity', false)
        ->assertSee('Reader copy')
        ->assertDontSee('ag-page-list__selection', false)
        ->assertDontSee('ag-page-list__row--selectable', false)
        ->assertDontSee('wire:click="selectCurrentPage"', false)
        ->assertDontSee('wire:click="deletePage('.$draft->id.')"', false)
        ->assertDontSee('page-actions-'.$draft->id, false)
        ->assertDontSee(route('admin.appearance.pages.edit', $draft), false);
});

test('the page form groups real metadata, editor and publication controls without changing editor hooks', function (): void {
    $manager = $this->createStaff([], ['pages.view', 'pages.manage']);
    $page = Page::query()->create(['title' => 'Existing page', 'slug' => 'existing-page', 'body' => 'Safe copy', 'status' => 'draft']);

    foreach ([route('admin.appearance.pages.create'), route('admin.appearance.pages.edit', $page)] as $url) {
        $this->actingAs($manager)->get($url)
            ->assertOk()
            ->assertSee('class="ag-card ag-page-form__metadata"', false)
            ->assertSee('class="ag-card ag-page-form__content" aria-labelledby="page-editor-label"', false)
            ->assertSee('class="ag-card ag-page-form__publication" aria-labelledby="page-status-heading"', false)
            ->assertSee('id="page-status-heading" class="ag-card__title"', false)
            ->assertDontSee('admin-panel ag-page-form', false)
            ->assertSee('ag-page-editor__toolbar', false)
            ->assertSee('id="page-editor"', false)
            ->assertSee('ag-page-editor__source', false)
            ->assertSee('x-data="agPageEditor"', false)
            ->assertSee('x-on:click="toggleSource()"', false)
            ->assertSee('wire:model="image"', false)
            ->assertSee('wire:model="imageAlt"', false)
            ->assertSee('wire:click="uploadImage"', false)
            ->assertSee('wire:model="status"', false)
            ->assertSee('wire:submit="save"', false);
    }
});
