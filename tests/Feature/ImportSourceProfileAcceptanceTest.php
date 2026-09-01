<?php

declare(strict_types=1);

use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Models\ImportRow;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

it('accepts complete source profile fixtures through the canonical import pipeline', function (string $source, string $email, string $productName, string $orderNumber): void {
    $registry = app(ImportAdapterRegistry::class);
    $executor = app(ImportExecutor::class);
    $fixtureRoot = base_path('tests/fixtures/imports/'.$source);

    foreach (['customer', 'product', 'order'] as $entity) {
        $run = $executor->run(
            $fixtureRoot.'/'.$entity.'.csv',
            $registry->for($source, $entity),
            $source,
        );

        expect($run->errors)->toBe(0)
            ->and($run->valid)->toBe(1)
            ->and(ImportRow::query()->where('import_run_id', $run->id)->where('status', 'imported')->count())->toBe(1);
    }

    $user = User::query()->where('email', $email)->first();
    $product = Product::query()->where('name', $productName)->first();
    $order = Order::query()->where('number', $orderNumber)->first();

    expect($user)->not->toBeNull()
        ->and($product)->not->toBeNull()
        ->and($product?->price_amount)->toBe(2500)
        ->and($order)->not->toBeNull()
        ->and($order?->customer_id)->toBe($user?->customer?->id)
        ->and($order?->total_amount)->toBe(2500)
        ->and($order?->items)->toHaveCount(1);
})->with([
    'hosting billing profile' => ['hosting_billing', 'hosting-customer@example.test', 'Hosting plan', 'HB-ORDER-1'],
    'billing platform profile' => ['billing_platform', 'billing-customer@example.test', 'Billing plan', 'BP-ORDER-1'],
    'shop platform profile' => ['shop_platform', 'shop-customer@example.test', 'Shop plan', 'SP-ORDER-1'],
    'commerce platform profile' => ['commerce_platform', 'commerce-customer@example.test', 'Commerce plan', 'CP-ORDER-1'],
]);
