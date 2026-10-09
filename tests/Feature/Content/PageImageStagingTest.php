<?php

declare(strict_types=1);

use App\Livewire\Admin\Content\PageForm;
use App\Models\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('keeps an inserted image temporary until save and publishes only a retained image', function (): void {
    Storage::fake('public');
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $form = Livewire::actingAs($staff)->test(PageForm::class)
        ->set('imageAlt', 'A chart')
        ->set('image', UploadedFile::fake()->image('chart.png'))
        ->call('uploadImage')->assertHasNoErrors();

    expect(Storage::disk('public')->files('pages'))->toBe([]);
    $preview = array_key_first($form->get('stagedImages'));
    expect($preview)->toContain('/preview-file/');
    $form->set('title', 'Chart page')
        ->set('body', '<p>Keep text</p><img src="'.e($preview).'" alt="A chart">')
        ->call('save')->assertHasNoErrors();

    $page = Page::query()->where('slug', 'chart-page')->sole();
    expect($page->body)->toContain('Keep text', 'alt="A chart"')
        ->not->toContain('/preview-file/');
    expect(Storage::disk('public')->files('pages'))->toHaveCount(1);
});

it('leaves canceled and unused images in Livewire temporary storage only', function (): void {
    Storage::fake('public');
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $form = Livewire::actingAs($staff)->test(PageForm::class)
        ->set('imageAlt', 'Unused')
        ->set('image', UploadedFile::fake()->image('unused.png'))
        ->call('uploadImage');
    $temporaryName = current($form->get('stagedImages'));
    $temporaryFile = TemporaryUploadedFile::createFromLivewire($temporaryName);
    expect($temporaryFile->exists())->toBeTrue();
    expect(Storage::disk('public')->files('pages'))->toBe([]);
    unset($form);
    expect(Storage::disk('public')->files('pages'))->toBe([]);

    $form = Livewire::actingAs($staff)->test(PageForm::class)
        ->set('imageAlt', 'Unused')
        ->set('image', UploadedFile::fake()->image('unused.png'))
        ->call('uploadImage')
        ->set('title', 'No image')
        ->set('body', '<p>Text remains</p>')
        ->call('save')->assertHasNoErrors();
    expect(Page::query()->where('slug', 'no-image')->sole()->body)->toBe('<p>Text remains</p>');
    expect(Storage::disk('public')->files('pages'))->toBe([]);
});

it('promotes two retained images once each and leaves unreferenced images temporary', function (): void {
    Storage::fake('public');
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $form = Livewire::actingAs($staff)->test(PageForm::class);
    foreach (['First', 'Second', 'Unused'] as $alt) {
        $form->set('imageAlt', $alt)
            ->set('image', UploadedFile::fake()->image(strtolower($alt).'.png'))
            ->call('uploadImage')->assertHasNoErrors();
    }
    [$first, $second] = array_slice(array_keys($form->get('stagedImages')), 0, 2);
    $form->set('title', 'Two images')
        ->set('body', '<p>Before</p><img src="'.e($first).'" alt="First"><img src="'.e($second).'" alt="Second"><img src="'.e($first).'" alt="First">')
        ->call('save')->assertHasNoErrors();
    expect(Storage::disk('public')->files('pages'))->toHaveCount(2);
    $body = Page::query()->where('slug', 'two-images')->sole()->body;
    expect(substr_count($body, '<img'))->toBe(3);
    expect($body)->toContain('alt="First"', 'alt="Second"', 'Before');
});

it('does not promote forged preview URLs or images removed by the sanitizer', function (): void {
    Storage::fake('public');
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $form = Livewire::actingAs($staff)->test(PageForm::class)
        ->set('imageAlt', 'Description')
        ->set('image', UploadedFile::fake()->image('chart.png'))
        ->call('uploadImage');
    $preview = array_key_first($form->get('stagedImages'));
    $forged = str_replace('signature=', 'signature=forged', $preview);
    $form->set('title', 'Forged')
        ->set('body', '<p>Text</p><img src="'.e($forged).'" alt="Forged"><img src="'.e($preview).'" alt=" ">')
        ->call('save')->assertHasNoErrors();
    expect(Page::query()->where('slug', 'forged')->sole()->body)->toContain('Text')->not->toContain('<img');
    expect(Storage::disk('public')->files('pages'))->toBe([]);
});

it('compensates published files when the database save fails without changing existing pages', function (): void {
    Storage::fake('public');
    $oldPath = 'pages/'.str_repeat('a', 40).'.png';
    Storage::disk('public')->put($oldPath, 'old image');
    $oldUrl = '/storage/'.$oldPath;
    $page = Page::query()->create([
        'title' => 'Existing', 'slug' => 'existing', 'status' => 'draft', 'body_format' => 'html',
        'body' => '<p>Old</p><img src="'.$oldUrl.'" alt="Shared image">',
    ]);
    Page::query()->create(['title' => 'Also uses image', 'slug' => 'also-uses-image', 'body_format' => 'html', 'status' => 'draft', 'body' => '<img src="'.$oldUrl.'" alt="Shared image">']);
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $form = Livewire::actingAs($staff)->test(PageForm::class, ['page' => $page])
        ->set('imageAlt', 'New')
        ->set('image', UploadedFile::fake()->image('new.png'))
        ->call('uploadImage');
    $preview = array_key_first($form->get('stagedImages'));
    Page::updating(static function (): void {
        throw new RuntimeException('Simulated database failure');
    });
    try {
        $form->set('body', '<p>Changed</p><img src="'.$oldUrl.'" alt="Shared image"><img src="'.e($preview).'" alt="New">')
            ->call('save');
        test()->fail('The save should have failed.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated database failure');
    } finally {
        Page::flushEventListeners();
    }
    expect($page->fresh()->body)->toContain('Old')->not->toContain('Changed');
    expect(Storage::disk('public')->files('pages'))->toBe([$oldPath]);
    Storage::disk('public')->assertExists($oldPath);
});

it('keeps shared historical images on a successful existing-page replacement', function (): void {
    Storage::fake('public');
    $oldPath = 'pages/'.str_repeat('b', 40).'.png';
    Storage::disk('public')->put($oldPath, 'shared image');
    $oldUrl = '/storage/'.$oldPath;
    $page = Page::query()->create(['title' => 'First page', 'slug' => 'first-page', 'body_format' => 'html', 'status' => 'draft', 'body' => '<img src="'.$oldUrl.'" alt="Old">']);
    $other = Page::query()->create(['title' => 'Second page', 'slug' => 'second-page', 'body_format' => 'html', 'status' => 'draft', 'body' => '<img src="'.$oldUrl.'" alt="Shared">']);
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $form = Livewire::actingAs($staff)->test(PageForm::class, ['page' => $page])
        ->set('imageAlt', 'Replacement')
        ->set('image', UploadedFile::fake()->image('new.png'))
        ->call('uploadImage');
    $preview = array_key_first($form->get('stagedImages'));
    $form->set('body', '<p>Edited</p><img src="'.e($preview).'" alt="Replacement">')
        ->call('save')->assertHasNoErrors();
    expect($page->fresh()->body)->toContain('Edited', 'alt="Replacement"')->not->toContain($oldUrl);
    expect($other->fresh()->body)->toContain($oldUrl);
    Storage::disk('public')->assertExists($oldPath);
    expect(Storage::disk('public')->files('pages'))->toHaveCount(2);
});

it('rejects client attempts to rewrite the locked staged-image mapping', function (): void {
    Storage::fake('public');
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    expect(fn () => Livewire::actingAs($staff)->test(PageForm::class)
        ->set('stagedImages', ['https://example.test/preview-file/forged' => 'forged.png']))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(Storage::disk('public')->files('pages'))->toBe([]);
});

it('never overwrites an existing public image when generating a promotion path', function (): void {
    Storage::fake('public');
    $oldPath = 'pages/'.str_repeat('a', 40).'.png';
    Storage::disk('public')->put($oldPath, 'shared original bytes');
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $form = Livewire::actingAs($staff)->test(PageForm::class)
        ->set('imageAlt', 'New chart')
        ->set('image', UploadedFile::fake()->image('new.png'))
        ->call('uploadImage');
    $preview = array_key_first($form->get('stagedImages'));
    Str::createRandomStringsUsingSequence([str_repeat('m', 40), str_repeat('a', 40), str_repeat('c', 40)]);
    try {
        $form->set('title', 'Collision safe')
            ->set('body', '<img src="'.e($preview).'" alt="New chart">')
            ->call('save')->assertHasNoErrors();
    } finally {
        Str::createRandomStringsNormally();
    }
    expect(Storage::disk('public')->get($oldPath))->toBe('shared original bytes');
    expect(Storage::disk('public')->files('pages'))->toHaveCount(2);
});

it('keeps the page unchanged when a retained temporary image has expired', function (): void {
    Storage::fake('public');
    $page = Page::query()->create(['title' => 'Existing', 'slug' => 'existing', 'body_format' => 'html', 'status' => 'draft', 'body' => '<p>Original</p>']);
    $staff = $this->createStaff([], ['pages.view', 'pages.manage']);
    $form = Livewire::actingAs($staff)->test(PageForm::class, ['page' => $page])
        ->set('imageAlt', 'Chart')
        ->set('image', UploadedFile::fake()->image('chart.png'))
        ->call('uploadImage');
    $preview = array_key_first($form->get('stagedImages'));
    $form->set('body', '<p>Changed</p><img src="'.e($preview).'" alt="Chart">');
    $temporary = TemporaryUploadedFile::createFromLivewire(current($form->get('stagedImages')));
    expect($temporary->delete())->toBeTrue();
    expect($temporary->exists())->toBeFalse();
    expect($form->instance()->body)->toContain('/preview-file/');
    expect($form->instance()->stagedImages)->toHaveKey($preview);
    try {
        $form->instance()->save();
        test()->fail('The missing temporary file should prevent saving.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('image');
    }
    expect($page->fresh()->body)->toBe('<p>Original</p>');
    expect(Storage::disk('public')->files('pages'))->toBe([]);
});
