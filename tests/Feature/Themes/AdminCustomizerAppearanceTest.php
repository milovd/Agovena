<?php

declare(strict_types=1);

use App\Agovena\Theme\ThemeManager;
use App\Livewire\Admin\Appearance\Customize;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function customizerXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
}

test('homepage composition separates its existing add actions from the section editors', function () {
    $html = Livewire\Livewire::actingAs($this->createStaff())
        ->test(Customize::class)
        ->set('tab', 'homepage')
        ->html();
    $xpath = customizerXpath($html);

    $layout = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' theme-customizer__home-layout ')]");
    expect($layout)->toHaveCount(1);
    $rail = $xpath->query("./aside[contains(concat(' ', normalize-space(@class), ' '), ' theme-customizer__section-rail ')]", $layout->item(0));
    $editor = $xpath->query("./div[contains(concat(' ', normalize-space(@class), ' '), ' theme-customizer__section-editor ')]", $layout->item(0));
    expect($rail)->toHaveCount(1)->and($editor)->toHaveCount(1);
    foreach (['hero', 'featured_products', 'featured_categories', 'promo_split', 'rich_text', 'trust_strip'] as $type) {
        expect($xpath->query(".//button[@*[name()='wire:click']=\"addSection('{$type}')\"]", $rail->item(0)))->toHaveCount(1);
    }
    expect($xpath->query('.//article[@*[name()="wire:key"]]', $editor->item(0)))->toHaveCount(5)
        ->and($xpath->query(".//button[starts-with(@*[name()='wire:click'], 'moveSection(')]", $editor->item(0)))->toHaveCount(10)
        ->and($xpath->query(".//button[starts-with(@*[name()='wire:click'], 'removeSection(')]", $editor->item(0)))->toHaveCount(5);
});

test('all four tabs retain their schema controls and existing repeater actions in both locales', function () {
    $staff = $this->createStaff();
    $groups = app(ThemeManager::class)->schemaFor(app(ThemeManager::class)->active())->grouped();
    $tabGroups = [
        'design' => ['appearance', 'branding'],
        'header' => ['header'],
        'storefront' => ['footer', 'catalog'],
        'homepage' => [],
    ];

    foreach (['en', 'nl'] as $locale) {
        app()->setLocale($locale);
        foreach ($tabGroups as $tab => $keys) {
            $html = Livewire\Livewire::actingAs($staff)->test(Customize::class)->set('tab', $tab)->html();
            $xpath = customizerXpath($html);
            $models = [];
            foreach ($xpath->query('//*[@*[name()="wire:model"]]') as $control) {
                $models[] = $control->getAttribute('wire:model');
            }
            $expected = [];
            foreach ($keys as $key) {
                foreach ($groups[$key] ?? [] as $field) {
                    if (! in_array($field->type, ['sections', 'usp_items'], true)) {
                        $expected[] = 'values.'.$field->key;
                    }
                }
                expect($xpath->query("//fieldset[@*[name()='wire:key']='group-{$key}']/legend/*[local-name()='svg']"))->toHaveCount(1);
            }
            foreach ($expected as $model) {
                expect($models)->toContain($model);
            }
            expect($xpath->query('//button[@type="submit"]'))->toHaveCount(1);
            if ($tab === 'header') {
                expect($models)->toContain('uspItems.0.text', 'uspItems.0.short', 'uspItems.0.emphasis', 'uspItems.0.href', 'uspItems.0.highlight');
                expect($xpath->query("//button[@*[name()='wire:click']='addUspItem']"))->toHaveCount(1);
            }
            if ($tab === 'homepage') {
                expect($models)->toContain('sections.0.eyebrow', 'sections.0.title', 'sections.0.lede', 'sections.0.cta_label', 'sections.0.cta_href', 'sections.4.items.0.title', 'sections.4.items.0.text');
                expect($xpath->query("//button[starts-with(@*[name()='wire:click'], 'addTrustItem(')]"))->toHaveCount(1);
                expect($xpath->query("//button[starts-with(@*[name()='wire:click'], 'removeTrustItem(')]"))->toHaveCount(4);
            }
        }
    }
});

test('view-only staff cannot open or mutate the customizer', function () {
    $staff = $this->createStaff([], ['theme.view']);
    $this->actingAs($staff)->get(route('admin.appearance.customize'))->assertForbidden();
    Livewire\Livewire::actingAs($staff)->test(Customize::class)->assertForbidden();
});
