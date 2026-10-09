<?php

declare(strict_types=1);

use App\Agovena\Theme\Theme;
use App\Livewire\Admin\Appearance\ThemesIndex;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('hides Customize from theme viewers and keeps the Themes list readable', function (): void {
    $viewer = $this->createStaff([], ['theme.view']);

    Livewire::actingAs($viewer)->test(ThemesIndex::class)
        ->assertSee(__('admin.appearance.themes.title'))
        ->assertDontSee(route('admin.appearance.customize'), false);
});

it('shows the shared theme save notice once', function (): void {
    $staff = $this->createStaff([], ['theme.view']);
    $response = $this->actingAs($staff)
        ->withSession(['status' => 'Theme saved once'])
        ->get(route('admin.appearance.themes'))
        ->assertOk();

    expect(substr_count($response->getContent(), 'Theme saved once'))->toBe(1);
});

it('shows management actions only to managers and confirms activation of another installed theme', function (): void {
    $alternate = new Theme('alternate', 'Alternate', '', '', basePath: base_path('themes/default'));
    $themes = [$alternate];
    $view = static fn (): string => view('livewire.admin.appearance.themes-index', [
        'themes' => $themes,
        'activeId' => 'default',
    ])->render();

    $this->actingAs($this->createStaff([], ['theme.view']));
    $readOnly = $view();
    expect($readOnly)->toContain('Alternate')
        ->not->toContain('wire:click="activate(', route('admin.appearance.customize'));

    $this->actingAs($this->createStaff([], ['theme.view', 'theme.manage']));
    $editable = $view();
    expect($editable)->toContain(route('admin.appearance.customize'))
        ->toContain('wire:click="activate(')
        ->toContain('wire:confirm=');
});
