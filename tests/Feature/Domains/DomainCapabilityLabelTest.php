<?php

declare(strict_types=1);

use App\Agovena\Settings\SettingsRepository;
use App\Models\Product;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/*
 * The Domains Module registers its product capability with its own translation keys. They
 * must resolve in every Admin locale; otherwise the product editor shows the raw key.
 */
test('the domain registration capability shows a translated label in the product editor', function (string $locale, string $label): void {
    if (! is_dir(optionalModuleRoot('provisioning').'/resources/views/admin')) {
        $this->markTestSkipped('This optional-packages revision predates the Domains capability translations.');
    }

    installAndEnableModule('domains');
    app(SettingsRepository::class)->set('general', 'locale', $locale);
    $product = Product::factory()->create();

    $this->actingAs($this->createStaff())
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee($label)
        ->assertDontSee('domains::admin.capabilities', false);
})->with([
    'English' => ['en', 'Domain registration'],
    'Dutch' => ['nl', 'Domeinregistratie'],
]);
