<?php

declare(strict_types=1);

use App\Agovena\Cart\CartSelectionCipher;
use App\Agovena\Cart\SessionCartRepository;
use App\Agovena\Cart\TokenCartRepository;
use App\Agovena\Catalog\Options\CartLineKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

test('session cart encrypts option selections and migrates plaintext v2 carts', function () {
    $session = app('session.store');
    $repository = new SessionCartRepository($session, app(CartSelectionCipher::class));
    $secret = 'FIVEM_LICENSE=do-not-store-this';

    $repository->add(41, 1, ['environment' => $secret]);

    expect(json_encode($session->get('agovena.cart'), JSON_THROW_ON_ERROR))
        ->not->toContain($secret)
        ->and($repository->lines()[0]->selections['environment'])
        ->toBe($secret);

    $session->put('agovena.cart', [
        'v' => 2,
        'lines' => [[
            'product_id' => 41,
            'quantity' => 1,
            'selections' => ['environment' => $secret],
        ]],
    ]);

    expect($repository->lines()[0]->selections['environment'])->toBe($secret)
        ->and(json_encode($session->get('agovena.cart'), JSON_THROW_ON_ERROR))
        ->not->toContain($secret)
        ->and($session->get('agovena.cart.v'))->toBe(3);
});

test('cart line keys use a keyed digest for non-empty option selections', function (): void {
    $selections = ['environment' => 'TEST_VALUE=fixture-only'];
    $firstKey = 'local-test-key-a';
    $secondKey = 'local-test-key-b';
    config()->set('app.key', $firstKey);

    $key = CartLineKey::make(42, $selections);
    $payload = '42:'.json_encode(CartLineKey::normalize($selections), JSON_THROW_ON_ERROR);

    expect($key)
        ->toBe('42:'.hash_hmac('sha256', $payload, $firstKey))
        ->and($key)->not->toBe('42:'.sha1($payload));

    config()->set('app.key', $secondKey);

    expect(CartLineKey::make(42, $selections))->not->toBe($key);
});

test('token cart encrypts option selections in cache', function () {
    $token = str_repeat('a', 64);
    $request = Request::create('/api/cart');
    $request->attributes->set('api_cart_token', $token);
    $cache = Cache::store('array');
    $repository = new TokenCartRepository($request, $cache, app(CartSelectionCipher::class));
    $secret = 'SERVER_LICENSE=not-a-real-secret';

    $repository->add(42, 1, ['environment' => $secret]);
    $stored = $cache->get('agovena.api-cart.token.'.$token);

    expect(json_encode($stored, JSON_THROW_ON_ERROR))
        ->not->toContain($secret)
        ->and($repository->lines()[0]->selections['environment'])
        ->toBe($secret);
});
