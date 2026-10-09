<?php

declare(strict_types=1);

use App\Livewire\Admin\Content\PagesIndex;
use App\Models\Page;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('an Admin cannot publish a page at a reserved storefront route', function (string $slug) {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->set('title', 'Reserved page')
        ->set('slug', $slug)
        ->set('status', 'published')
        ->call('save')
        ->assertHasErrors(['slug']);

    expect(Page::query()->where('slug', $slug)->exists())->toBeFalse();
})->with(['account', 'login', 'register']);

test('an Admin cannot save a page slug that cannot match the storefront URL', function () {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->set('title', 'Underscore page')
        ->set('slug', 'not_reachable')
        ->set('status', 'published')
        ->call('save')
        ->assertHasErrors(['slug']);

    expect(Page::query()->where('slug', 'not_reachable')->exists())->toBeFalse();
});

test('a valid published page remains reachable from the storefront route', function () {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->set('title', 'Reachable page')
        ->set('slug', 'reachable-page')
        ->set('status', 'published')
        ->call('save')
        ->assertHasNoErrors();

    expect(Page::query()->where('slug', 'reachable-page')->exists())->toBeTrue();

    $this->get(route('storefront.page', ['slug' => 'reachable-page']))
        ->assertOk()
        ->assertSee('Reachable page');
});
