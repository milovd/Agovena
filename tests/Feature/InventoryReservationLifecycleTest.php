<?php

declare(strict_types=1);

use Agovena\Modules\Inventory\InventoryService;
use Agovena\Modules\Inventory\Models\InventoryReservation;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Events\OrderCancelled;
use App\Models\Customer;
use App\Models\Product;

it('releases an inventory reservation once when cancellation is replayed', function (): void {
    installAndEnableModule('inventory');

    $product = Product::factory()->active()->create(['price_amount' => 1200]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'inventory');
    app(InventoryService::class)->setQuantity($product, 1);

    $customer = Customer::factory()->create();
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Reservation Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    expect(app(InventoryService::class)->quantityFor($product->fresh()))->toBe(0)
        ->and(InventoryReservation::query()->where('order_id', $order->id)->where('status', 'reserved')->count())->toBe(1);

    OrderCancelled::dispatch($order);
    OrderCancelled::dispatch($order);

    expect(app(InventoryService::class)->quantityFor($product->fresh()))->toBe(1)
        ->and(InventoryReservation::query()->where('order_id', $order->id)->where('status', 'released')->count())->toBe(1);
});
