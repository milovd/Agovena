<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\AddressAutocomplete\AddressAutocomplete;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.google_places.key' => 'test-places-key']);
});

test('address autocomplete returns google suggestions for a street query', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [
                [
                    'placePrediction' => [
                        'placeId' => 'ChIJtest123',
                        'structuredFormat' => [
                            'mainText' => ['text' => 'Damrak 1'],
                            'secondaryText' => ['text' => 'Amsterdam, Netherlands'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $suggestions = app(AddressAutocomplete::class)->suggest(
        'Damrak 1',
        'NL',
        'session-token-1',
    );

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]->id)->toBe('ChIJtest123')
        ->and($suggestions[0]->label)->toBe('Damrak 1')
        ->and($suggestions[0]->source)->toBe('google');
});

test('resolving a place fills street city postal region and country', function () {
    Http::fake([
        'places.googleapis.com/v1/places/*' => Http::response([
            'addressComponents' => [
                ['longText' => 'Damrak', 'shortText' => 'Damrak', 'types' => ['route']],
                ['longText' => '1', 'shortText' => '1', 'types' => ['street_number']],
                ['longText' => 'Amsterdam', 'shortText' => 'Amsterdam', 'types' => ['locality']],
                ['longText' => 'Noord-Holland', 'shortText' => 'NH', 'types' => ['administrative_area_level_1']],
                ['longText' => '1012 LG', 'shortText' => '1012 LG', 'types' => ['postal_code']],
                ['longText' => 'Netherlands', 'shortText' => 'NL', 'types' => ['country']],
            ],
        ], 200),
    ]);

    $resolved = app(AddressAutocomplete::class)->resolve('ChIJtest123', 'session-token-1');

    expect($resolved)->not->toBeNull()
        ->and($resolved->line1)->toBe('Damrak 1')
        ->and($resolved->city)->toBe('Amsterdam')
        ->and($resolved->postalCode)->toBe('1012 LG')
        ->and($resolved->region)->toBe('Noord-Holland')
        ->and($resolved->country)->toBe('NL');
});

test('checkout selecting a suggestion fills billing address fields', function () {
    Http::fake([
        'places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [
                [
                    'placePrediction' => [
                        'placeId' => 'ChIJfill',
                        'structuredFormat' => [
                            'mainText' => ['text' => 'Damrak 1'],
                            'secondaryText' => ['text' => 'Amsterdam, Netherlands'],
                        ],
                    ],
                ],
            ],
        ], 200),
        'places.googleapis.com/v1/places/*' => Http::response([
            'addressComponents' => [
                ['longText' => 'Damrak', 'shortText' => 'Damrak', 'types' => ['route']],
                ['longText' => '1', 'shortText' => '1', 'types' => ['street_number']],
                ['longText' => 'Amsterdam', 'shortText' => 'Amsterdam', 'types' => ['locality']],
                ['longText' => 'Noord-Holland', 'shortText' => 'NH', 'types' => ['administrative_area_level_1']],
                ['longText' => '1012 LG', 'shortText' => '1012 LG', 'types' => ['postal_code']],
                ['longText' => 'Netherlands', 'shortText' => 'NL', 'types' => ['country']],
            ],
        ], 200),
    ]);

    $product = Product::factory()->active()->create(['price_amount' => 1200]);
    app(CartService::class)->add($product->id, 1);

    Livewire::test(CheckoutPage::class)
        ->set('billing_line1', 'Dam')
        ->assertSet('addressSuggestions.0.id', 'ChIJfill')
        ->call('applyAddressSuggestion', 0)
        ->assertSet('billing_line1', 'Damrak 1')
        ->assertSet('billing_city', 'Amsterdam')
        ->assertSet('billing_postal_code', '1012 LG')
        ->assertSet('billing_region', 'Noord-Holland')
        ->assertSet('billing_country', 'NL')
        ->assertSet('addressSuggestions', []);
});

test('settings repository key also enables autocomplete', function () {
    config(['services.google_places.key' => null]);
    app(SettingsRepository::class)->set('store', 'google_places_api_key', 'settings-key');

    expect(app(AddressAutocomplete::class)->enabled())->toBeTrue();
});
