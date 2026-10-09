<?php

declare(strict_types=1);

use App\Livewire\Admin\Categories\Index as CategoriesIndex;
use App\Models\Category;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('shows loading feedback while searching or changing category pages without hiding the existing rows', function (): void {
    $category = Category::factory()->create(['name' => 'Hosting', 'slug' => 'hosting']);
    $staff = $this->createStaff([], ['categories.view']);

    Livewire::actingAs($staff)->test(CategoriesIndex::class)
        ->assertSee('wire:loading.flex', false)
        ->assertSee('wire:target="search,gotoPage,previousPage,nextPage"', false)
        ->assertSee(__('admin.categories.loading'))
        ->assertSee('wire:loading.class="is-loading"', false)
        ->assertSee('Hosting')
        ->assertDontSee('wire:click="edit('.$category->id.')"', false)
        ->assertDontSee('wire:click="confirmDelete('.$category->id.')"', false);
});

it('uses the shared Admin notice for category errors exactly once', function (): void {
    $staff = $this->createStaff([], ['categories.view']);
    $this->actingAs($staff);
    session()->flash('error', 'Category could not be removed');

    $response = $this->get(route('admin.categories.index'));

    $response->assertOk()->assertSee('Category could not be removed');
    expect(substr_count($response->getContent(), 'Category could not be removed'))->toBe(1);
});

it('keeps the searchable table, product counts, permissions and form entry points', function (): void {
    $parent = Category::factory()->create(['name' => 'Hardware', 'slug' => 'hardware']);
    $category = Category::factory()->create([
        'name' => 'Accessories', 'slug' => 'accessories', 'parent_id' => $parent->id,
    ]);
    Product::factory()->create(['category_id' => $category->id]);
    $staff = $this->createStaff();

    Livewire::actingAs($staff)->test(CategoriesIndex::class)
        ->assertSee('wire:model.live.debounce.300ms="search"', false)
        ->assertSee('Accessories')
        ->assertSee('Hardware')
        ->assertSee('<td>1</td>', false)
        ->assertSee('wire:click="edit('.$category->id.')"', false)
        ->assertSee('wire:click="confirmDelete('.$category->id.')"', false)
        ->call('edit', $category->id)
        ->assertSee('category-parent', false)
        ->assertSee('category-image', false)
        ->assertSee('category-active', false)
        ->set('search', 'no-category-matches-this')
        ->assertSee(__('admin.categories.empty.filtered_title'))
        ->assertDontSee('Accessories');
});
