<?php

declare(strict_types=1);

use App\Agovena\Content\SetSelectedPageStatus;
use App\Livewire\Admin\Content\PagesIndex;
use App\Models\AuditLog;
use App\Models\Page;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('renders page-only selection and confirmed status actions only to managers', function (): void {
    Page::query()->create(['title' => 'Example', 'slug' => 'example', 'status' => 'draft']);
    $manager = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($manager)->test(PagesIndex::class)
        ->assertSee('aria-label="'.__('admin.content.pages.bulk_select_row', ['title' => 'Example']).'"', false)
        ->assertDontSee('class="ag-checkbox__label"', false)
        ->assertSee('wire:click="bulkSetStatus(\'published\')"', false)
        ->assertSee('wire:confirm=', false);

    $viewer = $this->createStaff([], ['pages.view']);
    Livewire::actingAs($viewer)->test(PagesIndex::class)
        ->assertDontSee('selectedPageIds', false)
        ->assertDontSee('bulkSetStatus', false);
});

it('selects only server-visible filtered ids and clears selection on filter or page changes', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    foreach (range(1, 21) as $number) {
        Page::query()->create(['title' => sprintf('Page %02d', $number), 'slug' => 'page-'.$number, 'status' => 'draft']);
    }

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('search', 'Page 21')
        ->call('selectCurrentPage')
        ->assertSet('selectedPageIds', [21])
        ->set('search', '')
        ->assertSet('selectedPageIds', [])
        ->call('selectCurrentPage')
        ->assertCount('selectedPageIds', 20)
        ->call('gotoPage', 2)
        ->assertSet('selectedPageIds', [])
        ->call('selectCurrentPage')
        ->assertSet('selectedPageIds', [21])
        ->set('publicationFilter', 'published')
        ->assertSet('selectedPageIds', []);
});

it('limits execution to the filtered current page and reports forged off-page ids as skipped', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    foreach (range(1, 21) as $number) {
        Page::query()->create(['title' => sprintf('Page %02d', $number), 'slug' => 'page-'.$number, 'status' => 'draft']);
    }
    $visible = Page::query()->where('slug', 'page-1')->firstOrFail();
    $offPage = Page::query()->where('slug', 'page-21')->firstOrFail();

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('selectedPageIds', [(string) $visible->id, (string) $offPage->id, '999999'])
        ->call('bulkSetStatus', 'published')
        ->assertHasNoErrors()
        ->assertSee(__('admin.content.pages.bulk_result', ['updated' => 1, 'unchanged' => 0, 'skipped' => 2, 'failed' => 0]));

    expect($visible->fresh()->status)->toBe('published')
        ->and($offPage->fresh()->status)->toBe('draft')
        ->and(AuditLog::query()->where('action', 'page.updated')->count())->toBe(1);
});

it('does not let a forged selection cross the active search and status filters', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $matching = Page::query()->create(['title' => 'About store', 'slug' => 'about-store', 'status' => 'draft']);
    $wrongStatus = Page::query()->create(['title' => 'About live', 'slug' => 'about-live', 'status' => 'published']);
    $wrongSearch = Page::query()->create(['title' => 'Contact', 'slug' => 'contact', 'status' => 'draft']);

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('search', 'About')
        ->set('publicationFilter', 'draft')
        ->set('selectedPageIds', [(string) $matching->id, (string) $wrongStatus->id, (string) $wrongSearch->id])
        ->call('bulkSetStatus', 'published')
        ->assertSee(__('admin.content.pages.bulk_result', ['updated' => 1, 'unchanged' => 0, 'skipped' => 2, 'failed' => 0]));

    expect($matching->fresh()->status)->toBe('published')
        ->and($wrongStatus->fresh()->status)->toBe('published')
        ->and($wrongSearch->fresh()->status)->toBe('draft')
        ->and(AuditLog::query()->where('action', 'page.updated')->count())->toBe(1);
});

it('rejects oversized raw selections before deduplication or mutation', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $page = Page::query()->create(['title' => 'Example', 'slug' => 'example', 'status' => 'draft']);

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('selectedPageIds', array_fill(0, 501, (string) $page->id))
        ->call('bulkSetStatus', 'published')
        ->assertHasErrors(['selectedPageIds']);

    expect($page->fresh()->status)->toBe('draft')
        ->and(AuditLog::query()->where('action', 'page.updated')->exists())->toBeFalse();
});

it('allows drafting but does not audit duplicate ids or unchanged pages', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $published = Page::query()->create(['title' => 'Published', 'slug' => 'published', 'body' => 'Keep me', 'body_format' => 'plain', 'status' => 'published']);
    $draft = Page::query()->create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('selectedPageIds', [(string) $published->id, (string) $published->id, (string) $draft->id])
        ->call('bulkSetStatus', 'draft')
        ->assertSee(__('admin.content.pages.bulk_result', ['updated' => 1, 'unchanged' => 1, 'skipped' => 0, 'failed' => 0]));

    expect($published->fresh()->status)->toBe('draft')
        ->and($published->fresh()->body)->toBe('Keep me')
        ->and(AuditLog::query()->where('action', 'page.updated')->count())->toBe(1);
});

it('forbids mutation to readers even with a forged selection', function (): void {
    $viewer = $this->createStaff([], ['pages.view']);
    $page = Page::query()->create(['title' => 'Example', 'slug' => 'example', 'status' => 'draft']);

    Livewire::actingAs($viewer)->test(PagesIndex::class)
        ->set('selectedPageIds', [(string) $page->id])
        ->call('bulkSetStatus', 'published')
        ->assertForbidden();

    expect($page->fresh()->status)->toBe('draft')
        ->and(AuditLog::query()->where('action', 'page.updated')->exists())->toBeFalse();
});

it('does not audit missing or unauthorized records in the domain operation', function (): void {
    $viewer = $this->createStaff([], ['pages.view']);
    $page = Page::query()->create(['title' => 'Example', 'slug' => 'example', 'status' => 'draft']);
    $this->actingAs($viewer);

    $result = app(SetSelectedPageStatus::class)->handle([$page->id, 999999], 'published');

    expect($result)->toBe(['updated' => 0, 'unchanged' => 0, 'skipped' => 2, 'failed' => 0])
        ->and($page->fresh()->status)->toBe('draft')
        ->and(AuditLog::query()->where('action', 'page.updated')->exists())->toBeFalse();
});

it('does not allow a browser payload to fabricate the bulk result', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    expect(fn () => Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('bulkResult', ['updated' => 500, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('rejects invalid status and oversized direct domain requests', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $page = Page::query()->create(['title' => 'Example', 'slug' => 'example', 'status' => 'draft']);
    $this->actingAs($staff);
    $action = app(SetSelectedPageStatus::class);

    expect(fn () => $action->handle([$page->id], 'archived'))->toThrow(ValidationException::class)
        ->and(fn () => $action->handle(array_fill(0, 501, $page->id), 'published'))->toThrow(ValidationException::class)
        ->and($page->fresh()->status)->toBe('draft');
});

it('reports a per-record failure without rolling back a successful page', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $failed = Page::query()->create(['title' => 'Failure', 'slug' => 'failure', 'status' => 'draft']);
    $succeeded = Page::query()->create(['title' => 'Success', 'slug' => 'success', 'status' => 'draft']);
    Page::saving(static function (Page $page) use ($failed): void {
        if ($page->id === $failed->id) {
            throw new RuntimeException('Simulated page write failure');
        }
    });

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('selectedPageIds', [(string) $failed->id, (string) $succeeded->id])
        ->call('bulkSetStatus', 'published')
        ->assertSee(__('admin.content.pages.bulk_result', ['updated' => 1, 'unchanged' => 0, 'skipped' => 0, 'failed' => 1]));

    expect($failed->fresh()->status)->toBe('draft')
        ->and($succeeded->fresh()->status)->toBe('published')
        ->and(AuditLog::query()->where('action', 'page.updated')->count())->toBe(1);
});

it('publishes selected visible pages through the audited domain update without changing their content', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $first = Page::query()->create(['title' => 'First', 'slug' => 'first', 'body' => '<h2>Private content</h2>', 'body_format' => 'html', 'status' => 'draft']);
    $second = Page::query()->create(['title' => 'Second', 'slug' => 'second', 'body' => 'Plain content', 'body_format' => 'plain', 'status' => 'draft']);

    Livewire::actingAs($staff)->test(PagesIndex::class)
        ->set('selectedPageIds', [(string) $first->id, (string) $second->id])
        ->call('bulkSetStatus', 'published')
        ->assertHasNoErrors()
        ->assertSee(__('admin.content.pages.bulk_result', ['updated' => 2, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0]));

    expect($first->fresh()->only(['title', 'slug', 'body', 'body_format', 'status']))
        ->toMatchArray(['title' => 'First', 'slug' => 'first', 'body' => '<h2>Private content</h2>', 'body_format' => 'html', 'status' => 'published'])
        ->and($second->fresh()->only(['body', 'body_format', 'status']))
        ->toMatchArray(['body' => 'Plain content', 'body_format' => 'plain', 'status' => 'published'])
        ->and(AuditLog::query()->where('action', 'page.updated')->count())->toBe(2)
        ->and(json_encode(AuditLog::query()->where('action', 'page.updated')->firstOrFail()->toArray()))->not->toContain('Private content');
});
