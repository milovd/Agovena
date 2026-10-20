<?php

declare(strict_types=1);

use App\Livewire\Admin\Content\PagesIndex;
use App\Models\AuditLog;
use App\Models\Page;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('deletes the selected pages on the current page, audits each one and ignores forged ids', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $first = Page::query()->create(['title' => 'First', 'slug' => 'bulk-delete-first', 'status' => 'draft']);
    $second = Page::query()->create(['title' => 'Second', 'slug' => 'bulk-delete-second', 'status' => 'published']);
    $kept = Page::query()->create(['title' => 'Kept', 'slug' => 'bulk-delete-kept', 'status' => 'draft']);

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->assertSee('wire:click="bulkDeletePages"', false)
        ->assertSee('data-bulk-model="selectedPageIds"', false)
        ->set('selectedPageIds', array_map('strval', [$first->id, $second->id, 999999]))
        ->call('bulkDeletePages')
        ->assertHasNoErrors()
        ->assertSet('selectedPageIds', [])
        ->assertSet('bulkDeleteResult', ['deleted' => 2, 'skipped' => 1, 'failed' => 0])
        ->assertSee(__('admin.content.pages.bulk_delete_result', ['deleted' => 2, 'skipped' => 1, 'failed' => 0]));

    expect(Page::query()->whereKey([$first->id, $second->id])->exists())->toBeFalse()
        ->and($kept->fresh())->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'page.deleted')->count())->toBe(2);
});

it('refuses bulk page delete for viewers and without a selection', function (): void {
    $page = Page::query()->create(['title' => 'Only', 'slug' => 'bulk-delete-only', 'status' => 'draft']);

    Livewire::actingAs($this->createStaff([], ['pages.view']))->test(PagesIndex::class)
        ->assertDontSee('bulkDeletePages', false)
        ->set('selectedPageIds', [(string) $page->id])
        ->call('bulkDeletePages')
        ->assertForbidden();

    Livewire::actingAs($this->createStaff([], ['pages.view', 'pages.manage']))->test(PagesIndex::class)
        ->call('bulkDeletePages')
        ->assertHasErrors(['selectedPageIds']);

    expect($page->fresh())->not->toBeNull();
});
