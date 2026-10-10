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
        ->assertSee('wire:click="selectCurrentPage"', false)
        ->assertSee("wire:click=\"bulkSetStatus('published')\"", false)
        ->assertSee('wire:click="delete('.$draft->id.')"', false)
        ->assertSee(route('admin.appearance.pages.edit', $draft), false)
        ->assertSee(route('storefront.page', ['slug' => $published->slug]), false)
        ->assertSee('Private notes')
        ->assertSee('/private-notes');
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
        ->assertDontSee('wire:click="delete('.$draft->id.')"', false)
        ->assertDontSee(route('admin.appearance.pages.edit', $draft), false);
});

test('the page form groups real metadata, editor and publication controls without changing editor hooks', function (): void {
    $manager = $this->createStaff([], ['pages.view', 'pages.manage']);
    $page = Page::query()->create(['title' => 'Existing page', 'slug' => 'existing-page', 'body' => 'Safe copy', 'status' => 'draft']);

    foreach ([route('admin.appearance.pages.create'), route('admin.appearance.pages.edit', $page)] as $url) {
        $this->actingAs($manager)->get($url)
            ->assertOk()
            ->assertSee('ag-page-form__metadata', false)
            ->assertSee('ag-page-form__content', false)
            ->assertSee('ag-page-form__publication', false)
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
