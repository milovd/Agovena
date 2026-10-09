<?php

declare(strict_types=1);

use App\Livewire\Admin\Content\PageForm;
use App\Models\AuditLog;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('page managers can open a dedicated creation form from the page list', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    $this->actingAs($staff)
        ->get(route('admin.appearance.pages'))
        ->assertOk()
        ->assertSee(route('admin.appearance.pages.create'), false);

    $this->get(route('admin.appearance.pages.create'))
        ->assertOk()
        ->assertSee('wire:submit="save"', false)
        ->assertSee('agPageEditor(', false)
        ->assertSee('wire:ignore', false)
        ->assertSee('wire:model="image"', false)
        ->assertSee('wire:click="uploadImage"', false);
});

test('the dedicated page form creates audited HTML content', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PageForm::class)
        ->set('title', 'My Story')
        ->set('body', '<p>A private draft</p>')
        ->set('status', 'draft')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.appearance.pages'));

    $page = Page::query()->where('slug', 'my-story')->firstOrFail();
    expect($page->body)->toBe('<p>A private draft</p>')
        ->and($page->body_format)->toBe('html')
        ->and(AuditLog::query()->where('action', 'page.created')->sole()->after)->toMatchArray(['slug' => 'my-story', 'status' => 'draft']);
});

test('page editors get a dedicated edit URL that saves through the audited domain action', function (): void {
    $page = Page::query()->create(['title' => 'Before', 'slug' => 'before', 'body' => 'Plain before', 'status' => 'draft']);
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    $this->actingAs($staff)
        ->get(route('admin.appearance.pages.edit', $page))
        ->assertOk()
        ->assertSee('Plain before');

    Livewire::actingAs($staff)
        ->test(PageForm::class, ['page' => $page])
        ->set('body', 'Plain after')
        ->set('status', 'published')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.appearance.pages'));

    expect($page->fresh()->body)->toBe('Plain after')
        ->and(AuditLog::query()->where('action', 'page.updated')->sole()->after)->toMatchArray(['status' => 'published']);
});

test('editing a legacy plain-text page preserves encoded markup and line breaks', function (): void {
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $page = Page::query()->create([
        'title' => 'Legacy', 'slug' => 'legacy', 'body' => "One <script>\nTwo", 'status' => 'draft',
    ]);

    Livewire::actingAs($staff)
        ->test(PageForm::class, ['page' => $page])
        ->assertSet('body', '<p>One &lt;script&gt;<br>Two</p>')
        ->call('save')
        ->assertHasNoErrors();

    expect($page->fresh()->body_format)->toBe('html')
        ->and($page->fresh()->body)->toContain('&lt;script&gt;', '<br />');
});

test('view-only staff cannot open or submit page forms and see no management controls', function (): void {
    $page = Page::query()->create(['title' => 'Visible', 'slug' => 'visible', 'body' => 'Public', 'status' => 'published']);
    $viewer = $this->createStaff([], ['pages.view']);

    $this->actingAs($viewer)
        ->get(route('admin.appearance.pages'))
        ->assertOk()
        ->assertDontSee('wire:submit="save"', false)
        ->assertDontSee(route('admin.appearance.pages.create'), false)
        ->assertDontSee(route('admin.appearance.pages.edit', $page), false);
    $this->get(route('admin.appearance.pages.create'))->assertForbidden();
    $this->get(route('admin.appearance.pages.edit', $page))->assertForbidden();
    Livewire::actingAs($viewer)->test(PageForm::class)->assertForbidden();
});

test('the pages list shows the shared success notice only once', function (): void {
    $staff = $this->createStaff([], ['pages.view']);
    $this->actingAs($staff);
    session()->flash('status', 'Saved once');

    $response = $this->get(route('admin.appearance.pages'));
    $response->assertOk();
    expect(substr_count($response->getContent(), 'Saved once'))->toBe(1);
});

test('image insertion requires a managed image and alt text before publishing a local media URL', function (): void {
    Storage::fake('public');
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);

    Livewire::actingAs($staff)
        ->test(PageForm::class)
        ->set('image', UploadedFile::fake()->image('chart.png'))
        ->call('uploadImage')
        ->assertHasErrors(['imageAlt' => 'required']);
    expect(Storage::disk('public')->files('pages'))->toBe([]);

    Livewire::actingAs($staff)
        ->test(PageForm::class)
        ->set('imageAlt', 'Sales chart')
        ->set('image', UploadedFile::fake()->create('vector.svg', 2, 'image/svg+xml'))
        ->call('uploadImage')
        ->assertHasErrors(['image']);
    expect(Storage::disk('public')->files('pages'))->toBe([]);

    Livewire::actingAs($staff)
        ->test(PageForm::class)
        ->set('imageAlt', 'Sales chart')
        ->set('image', UploadedFile::fake()->image('chart.png'))
        ->call('uploadImage')
        ->assertHasNoErrors()
        ->assertDispatched('page-image-uploaded');

    $paths = Storage::disk('public')->files('pages');
    expect($paths)->toHaveCount(1);
    Storage::disk('public')->assertExists($paths[0]);
});
