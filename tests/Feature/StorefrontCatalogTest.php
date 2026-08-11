<?php

use App\Models\Product;

test('active products appear on storefront catalog', function () {
    $active = Product::factory()->active()->create(['name' => 'Visible Product']);
    Product::factory()->draft()->create(['name' => 'Hidden Draft']);

    $this->get('/')
        ->assertOk()
        ->assertSee('Visible Product', false)
        ->assertDontSee('Hidden Draft', false);

    $this->get(route('storefront.product', $active->slug))->assertOk();
});

test('draft products are not publicly listable or viewable', function () {
    $draft = Product::factory()->draft()->create(['name' => 'Secret Draft']);

    $this->get('/')->assertDontSee('Secret Draft', false);
    $this->get(route('storefront.product', $draft->slug))->assertNotFound();
});
