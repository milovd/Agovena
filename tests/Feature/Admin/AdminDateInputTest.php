<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

const ADMIN_DATE_INPUT_VIEWS = [
    'discounts/index',
    'exports/index',
    'invoices/edit',
    'orders/show',
    'referrals/index',
    'system/maintenance',
    'events/product-tab',
    'events/show',
];

test('the date input component renders the native input with every passed attribute and a translated trigger', function (string $locale, string $label) {
    app()->setLocale($locale);

    $html = Blade::render(
        '<x-ag.date-input id="due" class="ag-input" type="date" name="due" wire:model.live="dueAt" min="2026-01-01" max="2026-12-31" required aria-describedby="due-help" />'
    );

    expect($html)
        ->toContain('class="ag-date-input"')
        ->toContain('type="date"')
        ->toContain('id="due"')
        ->toContain('name="due"')
        ->toContain('wire:model.live="dueAt"')
        ->toContain('min="2026-01-01"')
        ->toContain('max="2026-12-31"')
        ->toContain('required')
        ->toContain('aria-describedby="due-help"')
        ->toContain('class="ag-input"')
        ->toContain('data-ag-calendar-trigger')
        ->toContain('type="button"')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('aria-label="'.$label.'"')
        ->toContain('<svg');

    expect(substr_count($html, '<input'))->toBe(1);
})->with([
    ['en', 'Open calendar'],
    ['nl', 'Kalender openen'],
]);

test('the date input component supports datetime-local and defaults to a date input', function () {
    expect(Blade::render('<x-ag.date-input id="a" type="datetime-local" wire:model="startsAt" />'))
        ->toContain('type="datetime-local"')
        ->toContain('wire:model="startsAt"');

    expect(Blade::render('<x-ag.date-input id="b" wire:model="day" />'))->toContain('type="date"');
});

test('the calendar labels exist in English and Dutch', function () {
    foreach (['en', 'nl'] as $locale) {
        foreach (['open_calendar', 'calendar', 'previous_month', 'next_month'] as $key) {
            expect(trans('common.'.$key, [], $locale))->not->toBe('common.'.$key);
        }
    }
});

test('every listed Admin view uses the date input component instead of a raw date input', function (string $view) {
    $source = file_get_contents(resource_path('views/livewire/admin/'.$view.'.blade.php'));

    expect($source)->toContain('<x-ag.date-input')
        ->and(preg_match('/<input[^>]*type="(date|datetime-local)"/s', $source))->toBe(0);
})->with(ADMIN_DATE_INPUT_VIEWS);

test('no raw date or datetime-local input remains in the Admin Livewire views', function () {
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views/livewire/admin')));

    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')
            && preg_match('/<input[^>]*type="(date|datetime-local)"/s', (string) file_get_contents($file->getPathname())) === 1) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});

test('the exports and maintenance screens render the enhanced date fields', function () {
    $owner = $this->createStaff();

    $this->actingAs($owner)->get('/admin/exports')
        ->assertOk()
        ->assertSee('id="data-export-created-from"', false)
        ->assertSee('wire:model="createdFrom"', false)
        ->assertSee('data-ag-calendar-trigger', false)
        ->assertSee(__('common.open_calendar'));

    $this->actingAs($owner)->get('/admin/maintenance')
        ->assertOk()
        ->assertSee('id="maintenance-ends-at"', false)
        ->assertSee('type="datetime-local"', false)
        ->assertSee('data-ag-calendar-trigger', false);
});
