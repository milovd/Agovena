<?php

declare(strict_types=1);

use App\Agovena\Theme\Theme;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('themes index renders real theme details in cards with an icon when the preview is missing', function (): void {
    $staff = $this->createStaff([], ['theme.view', 'theme.manage']);
    $html = $this->actingAs($staff)->get(route('admin.appearance.themes'))->assertOk()->getContent();

    expect($html)->toContain('class="c-theme-card"')
        ->toContain('class="c-theme-grid"')
        ->toContain('Official Agovena Theme - storefront and Admin presentation.')
        ->toContain('1.3.0')
        ->toContain(__('admin.appearance.themes.active'))
        ->toContain('class="c-theme-card__preview"')
        ->not->toContain('ag-table-wrap', '<table', 'src="'.route('admin.appearance.themes.preview', ['id' => 'default']).'"');
});

test('theme cards use the manifest id for preview and activation and keep management permission gates', function (): void {
    $theme = new Theme(
        id: 'manifest-theme', name: 'A & B Theme', viewsPath: '', cssEntry: '',
        version: '2.4.0', description: 'A real theme description',
        basePath: public_path('vendor/agovena'), previewReference: 'logo.png',
    );
    $render = static fn (): string => view('livewire.admin.appearance.themes-index', [
        'themes' => [$theme], 'activeId' => 'default',
    ])->render();

    $this->actingAs($this->createStaff([], ['theme.view']));
    $viewer = $render();
    expect($viewer)->toContain('class="c-theme-card"')
        ->toContain('A &amp; B Theme', 'A real theme description', '2.4.0')
        ->toContain(route('admin.appearance.themes.preview', ['id' => 'manifest-theme']))
        ->toContain(__('admin.appearance.themes.installed'))
        ->not->toContain('wire:click="activate(', route('admin.appearance.customize'));

    $this->actingAs($this->createStaff([], ['theme.view', 'theme.manage']));
    $manager = $render();
    expect($manager)->toContain('wire:click="activate(\'manifest-theme\')"')
        ->toContain('wire:confirm=')
        ->toContain(route('admin.appearance.customize'));
});

test('active theme has no activation button and empty themes keep the existing guidance', function (): void {
    $this->actingAs($this->createStaff([], ['theme.view', 'theme.manage']));
    $theme = new Theme('active-theme', 'Active Theme', '', '', basePath: base_path('themes/default'));
    $activeHtml = view('livewire.admin.appearance.themes-index', [
        'themes' => [$theme], 'activeId' => 'active-theme',
    ])->render();
    expect($activeHtml)->toContain(__('admin.appearance.themes.active'))
        ->not->toContain('wire:click="activate(');

    $emptyHtml = view('livewire.admin.appearance.themes-index', [
        'themes' => [], 'activeId' => 'default',
    ])->render();
    expect($emptyHtml)->toContain(__('admin.appearance.themes.empty_title'))
        ->toContain('themes/{id}', 'theme.json', 'role="status"');
});
