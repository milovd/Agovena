<?php

declare(strict_types=1);

use App\Agovena\Availability\InventoryService;
use App\Agovena\Availability\Models\InventoryReservation;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Orders\Lines\OrderLineDraft;
use App\Agovena\Orders\SaveOrderChanges;
use App\Agovena\Physical\Enums\ShipmentStatus;
use App\Agovena\Physical\Models\Shipment;
use App\Agovena\Physical\Models\ShipmentItem;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\DiscountRedemption;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

beforeEach(function (): void {
    // Totals in these tests are plain sums; tax has its own coverage.
    app(SettingsRepository::class)->set('store', 'tax_enabled', false);
});

/** @param list<array{0: Product, 1: int}> $lines */
function placeLineOrder(array $lines, ?string $discountCode = null): Order
{
    $customer = Customer::factory()->create();
    foreach ($lines as [$product, $quantity]) {
        app(CartService::class)->add($product->id, $quantity);
    }

    return app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'discount_code' => $discountCode,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Line Street 1',
            'city' => 'Ghent',
            'postal_code' => '9000',
            'country' => 'BE',
        ]),
    ])->fresh(['items', 'payment']);
}

/** @return list<OrderLineDraft> */
function draftsFor(Order $order): array
{
    return $order->items->map(static fn (OrderItem $item): OrderLineDraft => new OrderLineDraft(
        (int) $item->id,
        (int) $item->product_id,
        (int) $item->quantity,
        (int) $item->unit_amount,
    ))->values()->all();
}

it('edits, adds and removes lines on an unpaid order and recalculates every total', function (): void {
    $staff = $this->createStaff();
    $a = Product::factory()->active()->create(['price_amount' => 1000]);
    $b = Product::factory()->active()->create(['price_amount' => 500]);
    $c = Product::factory()->active()->create(['price_amount' => 250, 'name' => 'Added Product']);
    $order = placeLineOrder([[$a, 1], [$b, 2]]);
    $oldInvoice = Invoice::query()->where('order_id', $order->id)->sole();

    $drafts = draftsFor($order);
    $first = $drafts[0];
    $drafts = [
        new OrderLineDraft($first->id, $first->productId, 3, 1200),
        new OrderLineDraft(null, $c->id, 2, 250),
    ];

    $updated = app(SaveOrderChanges::class)->handle($order, $staff, lines: $drafts);

    expect($updated->items)->toHaveCount(2)
        ->and($updated->items->firstWhere('product_id', $a->id)->line_total_amount)->toBe(3600)
        ->and($updated->items->firstWhere('product_id', $c->id)->label)->toBe('Added Product')
        ->and($updated->subtotal_amount)->toBe(4100)
        ->and($updated->total_amount)->toBe(4100 + (int) $updated->shipping_amount + (int) $updated->payment_fee_amount)
        ->and($updated->payment->amount)->toBe($updated->total_amount)
        ->and($updated->payment->status)->toBe(PaymentStatus::Pending)
        ->and($oldInvoice->fresh()->status)->toBe(InvoiceStatus::Void);

    $replacement = Invoice::query()->where('order_id', $order->id)->where('status', InvoiceStatus::Issued)->sole();
    expect($replacement->total_amount)->toBe($updated->total_amount)
        ->and($replacement->items)->toHaveCount(2)
        ->and(AuditLog::query()->where('action', 'order.lines_updated')->exists())->toBeTrue();
});

it('recalculates a percentage discount and its redemption after a line change', function (): void {
    $staff = $this->createStaff();
    DiscountCode::query()->create(['code' => 'TENOFF', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
    $a = Product::factory()->active()->create(['price_amount' => 1000]);
    $order = placeLineOrder([[$a, 1]], 'TENOFF');
    expect($order->discount_amount)->toBe(100);

    $line = draftsFor($order)[0];
    $updated = app(SaveOrderChanges::class)->handle($order, $staff, lines: [
        new OrderLineDraft($line->id, $line->productId, 4, 1000),
    ]);

    expect($updated->subtotal_amount)->toBe(4000)
        ->and($updated->discount_amount)->toBe(400)
        ->and(DiscountRedemption::query()->where('order_id', $order->id)->sole()->amount)->toBe(400);
});

it('refuses line changes on paid orders, with account credit and without lines', function (): void {
    $staff = $this->createStaff();
    $a = Product::factory()->active()->create(['price_amount' => 1000]);

    $paid = placeLineOrder([[$a, 1]]);
    $paid->payment->update(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    $line = draftsFor($paid)[0];
    expect(fn () => app(SaveOrderChanges::class)->handle($paid->fresh(), $staff, lines: [
        new OrderLineDraft($line->id, $line->productId, 2, 1000),
    ]))->toThrow(ValidationException::class);

    $credited = placeLineOrder([[$a, 1]]);
    $credited->update(['credit_amount' => 100]);
    $line = draftsFor($credited)[0];
    expect(fn () => app(SaveOrderChanges::class)->handle($credited->fresh(), $staff, lines: [
        new OrderLineDraft($line->id, $line->productId, 2, 1000),
    ]))->toThrow(ValidationException::class);

    $open = placeLineOrder([[$a, 1]]);
    expect(fn () => app(SaveOrderChanges::class)->handle($open, $staff, lines: []))
        ->toThrow(ValidationException::class);

    expect($paid->fresh()->items->sole()->quantity)->toBe(1)
        ->and($credited->fresh()->items->sole()->quantity)->toBe(1)
        ->and($open->fresh()->items)->toHaveCount(1);
});

it('keeps lines of capabilities that did not opt in to line editing, except for the price', function (): void {
    $staff = $this->createStaff();
    app(ProductCapabilityRegistry::class)->register(new ProductCapabilityDefinition(
        key: 'test-locked-lines',
        label: 'Locked lines',
    ));
    $locked = Product::factory()->active()->create(['price_amount' => 1000]);
    $plain = Product::factory()->active()->create(['price_amount' => 700]);
    $order = placeLineOrder([[$locked, 1]]);
    app(ProductCapabilityManager::class)->enable($locked, 'test-locked-lines');
    $line = draftsFor($order)[0];

    foreach ([
        [new OrderLineDraft($line->id, $line->productId, 2, 1000)],
        [new OrderLineDraft($line->id, $plain->id, 1, 700)],
        [$line, new OrderLineDraft(null, $locked->id, 1, 1000)],
        [new OrderLineDraft(null, $plain->id, 1, 700)],
    ] as $attempt) {
        expect(fn () => app(SaveOrderChanges::class)->handle($order->fresh(['items', 'payment']), $staff, lines: $attempt))
            ->toThrow(ValidationException::class);
    }

    $updated = app(SaveOrderChanges::class)->handle($order->fresh(['items', 'payment']), $staff, lines: [
        new OrderLineDraft($line->id, $line->productId, 1, 850),
    ]);
    expect($updated->items->sole()->unit_amount)->toBe(850)
        ->and($updated->subtotal_amount)->toBe(850);
});

it('moves stock reservations with quantity changes and removals and refuses more than is in stock', function (): void {
    installAndEnableModule('inventory');
    $staff = $this->createStaff();
    $stocked = Product::factory()->active()->create(['price_amount' => 1000]);
    app(ProductCapabilityManager::class)->enable($stocked, 'physical');
    app(ProductCapabilityManager::class)->enable($stocked, 'inventory');
    app(InventoryService::class)->setQuantity($stocked, 10);
    $other = Product::factory()->active()->create(['price_amount' => 500]);
    $order = placeLineOrder([[$stocked, 2]]);
    $inventory = app(InventoryService::class);
    expect($inventory->quantityFor($stocked->fresh()))->toBe(8);

    $line = draftsFor($order)[0];
    app(SaveOrderChanges::class)->handle($order, $staff, lines: [
        new OrderLineDraft($line->id, $line->productId, 5, 1000),
    ]);
    expect($inventory->quantityFor($stocked->fresh()))->toBe(5)
        ->and(InventoryReservation::query()->where('order_item_id', $line->id)->sole()->quantity)->toBe(5);

    expect(fn () => app(SaveOrderChanges::class)->handle($order->fresh(['items', 'payment']), $staff, lines: [
        new OrderLineDraft($line->id, $line->productId, 11, 1000),
    ]))->toThrow(ValidationException::class);
    expect($inventory->quantityFor($stocked->fresh()))->toBe(5)
        ->and($order->fresh()->items->sole()->quantity)->toBe(5);

    app(SaveOrderChanges::class)->handle($order->fresh(['items', 'payment']), $staff, lines: [
        new OrderLineDraft(null, $other->id, 1, 500),
    ]);
    expect($inventory->quantityFor($stocked->fresh()))->toBe(10)
        ->and(InventoryReservation::query()->where('order_id', $order->id)->where('status', 'reserved')->count())->toBe(0);
});

it('keeps a pending shipment in sync and refuses shippable lines without one', function (): void {
    $staff = $this->createStaff();
    $shippable = Product::factory()->active()->create(['price_amount' => 1000]);
    app(ProductCapabilityManager::class)->enable($shippable, 'physical');
    app(ProductCapabilityManager::class)->enable($shippable, 'shippable');
    $digital = Product::factory()->active()->create(['price_amount' => 300]);

    $order = placeLineOrder([[$digital, 1]]);
    $line = draftsFor($order)[0];
    expect(fn () => app(SaveOrderChanges::class)->handle($order, $staff, lines: [
        $line,
        new OrderLineDraft(null, $shippable->id, 1, 1000),
    ]))->toThrow(ValidationException::class);

    $shipment = Shipment::query()->create([
        'order_id' => $order->id,
        'status' => ShipmentStatus::Pending,
        'shipping_amount' => 0,
        'currency' => $order->currency,
    ]);
    $updated = app(SaveOrderChanges::class)->handle($order->fresh(['items', 'payment']), $staff, lines: [
        $line,
        new OrderLineDraft(null, $shippable->id, 2, 1000),
    ]);
    $shippableItem = $updated->items->firstWhere('product_id', $shippable->id);
    expect(ShipmentItem::query()->where('shipment_id', $shipment->id)->where('order_item_id', $shippableItem->id)->sole()->quantity)->toBe(2);

    app(SaveOrderChanges::class)->handle($updated, $staff, lines: [
        $line,
        new OrderLineDraft((int) $shippableItem->id, $shippable->id, 3, 1000),
    ]);
    expect(ShipmentItem::query()->where('order_item_id', $shippableItem->id)->sole()->quantity)->toBe(3);

    $shipment->update(['status' => ShipmentStatus::Shipped]);
    expect(fn () => app(SaveOrderChanges::class)->handle($order->fresh(['items', 'payment']), $staff, lines: [$line]))
        ->toThrow(ValidationException::class);
});

it('saves customer, details, lines and addresses together and issues one replacement invoice', function (): void {
    $staff = $this->createStaff();
    $a = Product::factory()->active()->create(['price_amount' => 1000]);
    $order = placeLineOrder([[$a, 1]]);
    $newOwner = Customer::factory()->create(['name' => 'Together Owner', 'email' => 'together@example.test']);
    $line = draftsFor($order)[0];

    $updated = app(SaveOrderChanges::class)->handle(
        $order,
        $staff,
        customer: $newOwner,
        attributes: ['customer_name' => 'Together Owner', 'customer_email' => 'together@example.test', 'billing_city' => 'Leuven'],
        lines: [new OrderLineDraft($line->id, $line->productId, 2, 1000)],
    );

    expect($updated->customer_id)->toBe($newOwner->id)
        ->and($updated->billing_city)->toBe('Leuven')
        ->and($updated->subtotal_amount)->toBe(2000)
        ->and(Invoice::query()->where('order_id', $order->id)->where('status', InvoiceStatus::Void)->count())->toBe(1)
        ->and(Invoice::query()->where('order_id', $order->id)->where('status', InvoiceStatus::Issued)->sole()->customer_email)->toBe('together@example.test');
});
