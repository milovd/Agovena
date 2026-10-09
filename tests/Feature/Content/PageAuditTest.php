<?php

declare(strict_types=1);

use App\Livewire\Admin\Content\PagesIndex;
use App\Models\AuditLog;
use App\Models\Page;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('creating a page records its non-sensitive publication details', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->set('title', 'About Agovena')
        ->set('slug', 'about-agovena')
        ->set('body', 'Private editorial draft')
        ->set('status', 'draft')
        ->call('save')
        ->assertHasNoErrors();

    $page = Page::query()->where('slug', 'about-agovena')->firstOrFail();
    $log = AuditLog::query()->where('action', 'page.created')->sole();

    expect($log->subject_id)->toBe($page->id)
        ->and($log->category)->toBe('admin')
        ->and($log->before)->toBeNull()
        ->and($log->after)->toMatchArray(['title' => 'About Agovena', 'slug' => 'about-agovena', 'status' => 'draft'])
        ->and(json_encode($log->toArray()))->not->toContain('Private editorial draft');
});

test('editing a page records changed publication details without copying its body into the audit', function (): void {
    $page = Page::query()->create(['title' => 'Old title', 'slug' => 'old-title', 'body' => 'Initial draft', 'status' => 'draft']);
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->call('edit', $page->id)
        ->set('title', 'New title')
        ->set('body', 'Private revision')
        ->set('status', 'published')
        ->call('save')
        ->assertHasNoErrors();

    $log = AuditLog::query()->where('action', 'page.updated')->sole();

    expect($page->fresh()->title)->toBe('New title')
        ->and($log->subject_id)->toBe($page->id)
        ->and($log->before)->toMatchArray(['title' => 'Old title', 'status' => 'draft'])
        ->and($log->after)->toMatchArray(['title' => 'New title', 'status' => 'published'])
        ->and(json_encode($log->toArray()))->not->toContain('Private revision');
});

test('deleting a page records the removed publication details without exposing its body', function (): void {
    $page = Page::query()->create(['title' => 'Temporary', 'slug' => 'temporary', 'body' => 'Secret draft', 'status' => 'draft']);
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->call('delete', $page->id)
        ->assertHasNoErrors();

    $log = AuditLog::query()->where('action', 'page.deleted')->sole();

    expect(Page::query()->whereKey($page->id)->exists())->toBeFalse()
        ->and($log->subject_id)->toBe($page->id)
        ->and($log->before)->toMatchArray(['title' => 'Temporary', 'slug' => 'temporary', 'status' => 'draft'])
        ->and($log->after)->toBeNull()
        ->and(json_encode($log->toArray()))->not->toContain('Secret draft');
});

test('saving an unchanged page does not create an audit event', function (): void {
    $page = Page::query()->create(['title' => 'About', 'slug' => 'about', 'body' => 'Same body', 'status' => 'draft']);
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->call('edit', $page->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(AuditLog::query()->where('action', 'page.updated')->exists())->toBeFalse();
});

test('a staff member without page management cannot delete or audit a page', function (): void {
    $page = Page::query()->create(['title' => 'About', 'slug' => 'about', 'body' => 'Content', 'status' => 'draft']);
    $viewer = $this->createStaff([], ['pages.view']);

    Livewire::actingAs($viewer)
        ->test(PagesIndex::class)
        ->call('delete', $page->id)
        ->assertForbidden();

    expect(Page::query()->whereKey($page->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'page.deleted')->exists())->toBeFalse();
});

test('deleting an already missing page preserves the existing no-op behavior without a false audit', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->call('delete', 999999)
        ->assertHasNoErrors();

    expect(AuditLog::query()->where('action', 'page.deleted')->exists())->toBeFalse();
});

test('updating a now-missing page preserves the previous no-op behavior without false audit', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PagesIndex::class)
        ->set('editingId', 999999)
        ->set('title', 'Removed')
        ->set('slug', 'removed')
        ->set('body', 'No content')
        ->call('save')
        ->assertHasNoErrors();

    expect(Page::query()->where('slug', 'removed')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'page.updated')->exists())->toBeFalse();
});
