<?php

declare(strict_types=1);

use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Settings\Hub;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('view-only settings expose focusable read-only values without a submit form', function (string $locale): void {
    app()->setLocale($locale);
    $viewer = $this->createStaff(permissions: ['settings.view']);
    $node = static function (string $html, string $id): DOMElement {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $element = (new DOMXPath($document))->query('//*[@id="'.$id.'"]')->item(0);
        expect($element)->toBeInstanceOf(DOMElement::class);

        return $element;
    };
    $assertReadOnly = static function (string $html, string $id) use ($node): void {
        $element = $node($html, $id);
        expect($element->hasAttribute('readonly'))->toBeTrue()
            ->and($element->hasAttribute('disabled'))->toBeFalse();
    };

    $page = Livewire::actingAs($viewer)->test(Hub::class)
        ->assertDontSee('wire:submit="save"', false);
    $assertReadOnly($page->html(), 'setting-site_name');
    $assertReadOnly($page->html(), 'setting-timezone');
    expect($node($page->html(), 'setting-locale')->hasAttribute('disabled'))->toBeTrue();

    $page->set('tab', 'mail');
    $assertReadOnly($page->html(), 'setting-from_address');

    $page->set('tab', 'store');
    $assertReadOnly($page->html(), 'setting-seller_address');
    $assertReadOnly($page->html(), 'setting-unpaid_order_cancel_after_days');
    expect($node($page->html(), 'setting-google_places_api_key')->hasAttribute('disabled'))->toBeTrue();
    expect($node($page->html(), 'setting-tax_enabled')->hasAttribute('disabled'))->toBeTrue();
})->with(['en', 'nl']);

test('branding uploads render as images rather than inactive upload actions for view-only staff', function (string $locale): void {
    app()->setLocale($locale);
    $viewer = $this->createStaff(permissions: ['settings.view']);

    Livewire::actingAs($viewer)
        ->test(Hub::class)
        ->set('tab', 'branding')
        ->assertSee('ag-file-upload__surface', false)
        ->assertSee(__('common.no_image'))
        ->assertDontSee('ag-file-upload__trigger', false)
        ->assertDontSee('type="file"', false)
        ->assertDontSee('wire:model="uploads.', false);
})->with(['en', 'nl']);

test('view-only branding describes a stored image whose file is unavailable accurately', function (string $locale, string $message): void {
    app()->setLocale($locale);
    Storage::fake('public');
    app(SettingsRepository::class)->set('branding', 'logo_path', 'branding/missing-image.png');
    $viewer = $this->createStaff(permissions: ['settings.view']);

    Livewire::actingAs($viewer)
        ->test(Hub::class)
        ->set('tab', 'branding')
        ->assertSee($message)
        ->assertDontSee('No image uploaded')
        ->assertDontSee('Geen afbeelding geüpload');
})->with([
    ['en', 'No image available'],
    ['nl', 'Geen afbeelding beschikbaar'],
]);

test('settings managers retain upload controls and a submit form', function (): void {
    $manager = $this->createStaff();

    Livewire::actingAs($manager)
        ->test(Hub::class)
        ->set('tab', 'branding')
        ->assertSee('wire:submit="save"', false)
        ->assertSee('type="file"', false)
        ->assertSee('wire:model="uploads.logo_path"', false)
        ->assertSee('ag-file-upload__trigger', false);
});
