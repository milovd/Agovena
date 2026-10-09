<?php

declare(strict_types=1);

use App\Livewire\Admin\Content\PagesIndex;
use App\Models\Page;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('combines title or slug search with publication status without repurposing the legacy form status', function (): void {
    $staff = $this->createStaff([], ['pages.view']);
    $match = Page::query()->create(['title' => 'About the store', 'slug' => 'our-story', 'body' => '', 'status' => 'published']);
    Page::query()->create(['title' => 'About draft', 'slug' => 'about-draft', 'body' => '', 'status' => 'draft']);
    Page::query()->create(['title' => 'Other', 'slug' => 'other', 'body' => '', 'status' => 'published']);

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('search', 'about')
        ->set('publicationFilter', 'published')
        ->assertSee($match->title)
        ->assertDontSee('About draft')
        ->assertDontSee('Other')
        ->assertSet('status', 'draft')
        ->set('search', 'our-story')
        ->assertSee($match->title);
});

it('resets pagination on filter changes while retaining a twenty per page title order', function (): void {
    $staff = $this->createStaff([], ['pages.view']);
    foreach (range(1, 21) as $number) {
        Page::query()->create(['title' => sprintf('Page %02d', $number), 'slug' => 'page-'.$number, 'body' => '', 'status' => 'published']);
    }

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->assertSee('Page 01')
        ->assertDontSee('Page 21')
        ->call('gotoPage', 2)
        ->assertSee('Page 21')
        ->set('search', 'Page 01')
        ->assertSee('Page 01')
        ->set('search', '')
        ->call('gotoPage', 2)
        ->set('publicationFilter', 'draft')
        ->assertSee(__('admin.content.pages.filtered_empty_title'));
});

it('keeps management actions hidden from readers and distinguishes filtered empty pages in both languages', function (): void {
    $viewer = $this->createStaff([], ['pages.view']);
    foreach (['en', 'nl'] as $locale) {
        app()->setLocale($locale);
        Livewire::actingAs($viewer)->test(PagesIndex::class)
            ->assertSee(__('admin.content.pages.empty_title'))
            ->set('search', 'not-here')
            ->assertSee(__('admin.content.pages.filtered_empty_title'))
            ->assertDontSee('wire:click="delete', false)
            ->assertDontSee(route('admin.appearance.pages.create'), false);
    }
});
