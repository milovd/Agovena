<?php

declare(strict_types=1);

use Agovena\Modules\Shipping\Enums\ReturnRequestStatus;
use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Http\Livewire\Admin\ReturnShow;
use Agovena\Modules\Shipping\Http\Livewire\Customer\ReturnCreate;
use Agovena\Modules\Shipping\Models\ReturnRequest;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use Agovena\Modules\Shipping\ReturnRequestService;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Contracts\ProductStock;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableReturns(bool $withInventory = false): void
{
    app(ModuleManager::class)->enable('shipping');
    if ($withInventory) {
        app(ModuleManager::class)->enable('inventory');
    }
    app(SyncRegisteredPermissions::class)(force: true);
}

function returnsBilling(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Return Tester',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

function returnableProduct(bool $withInventory = false): Product
{
    $product = Product::factory()->active()->create(['price_amount' => 2000]);
    app(ProductCapabilityManager::class)->enable($product, 'physical');
    app(ProductCapabilityManager::class)->enable($product, 'shippable', ['weight_grams' => 500]);
    if ($withInventory) {
        app(ProductCapabilityManager::class)->enable($product, 'inventory');
    }

    return $product->fresh(['capabilities']);
}

function returnsShippingMethod(): ShippingMethod
{
    return ShippingMethod::query()->create([
        'name' => 'Standard',
        'code' => 'returns-standard-'.uniqid(),
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 500],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 10,
    ]);
}

/**
 * Places a paid order for the given customer and returns it.
 */
function paidReturnableOrder(Customer $customer, Product $product, int $quantity, User $staff): Order
{
    app(CartService::class)->add($product->id, $quantity);

    $order = app(PlaceOrder::class)->handle([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'billing' => returnsBilling(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => returnsShippingMethod()->id,
    ]);

    app(RecordManualPayment::class)->handle($order, $staff);

    return $order->fresh(['items', 'payment']);
}

test('customer requests a return for a paid shippable order', function () {
    enableReturns();
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = returnableProduct();

    $order = paidReturnableOrder($customer, $product, 2, $staff);
    $item = $order->items->firstOrFail();

    $request = app(ReturnRequestService::class)->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $item->id, 'quantity' => 1]],
        'Wrong size',
        $customer,
    );

    expect($request->status)->toBe(ReturnRequestStatus::Requested)
        ->and($request->customer_id)->toBe($customer->id)
        ->and($request->reason)->toBe('Wrong size')
        ->and($request->requested_at)->not->toBeNull()
        ->and($request->items)->toHaveCount(1)
        ->and($request->items->first()->quantity)->toBe(1);

    $this->actingAs($customer->user)
        ->get(route('customer.returns'))
        ->assertOk()
        ->assertSee($order->number);
});

test('customer creates a return from the account portal', function () {
    enableReturns();
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = returnableProduct();

    $order = paidReturnableOrder($customer, $product, 2, $staff);
    $itemId = (int) $order->items->firstOrFail()->id;

    $this->actingAs($customer->user)
        ->get(route('customer.returns.create', $order))
        ->assertOk();

    Livewire::actingAs($customer->user)
        ->test(ReturnCreate::class, ['order' => $order])
        ->set('quantities', [$itemId => 2])
        ->set('reason', 'Both are faulty')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('customer.returns'));

    $request = ReturnRequest::query()->where('order_id', $order->id)->with('items')->firstOrFail();

    expect($request->status)->toBe(ReturnRequestStatus::Requested)
        ->and($request->items->firstOrFail()->quantity)->toBe(2);

    // The whole line is now spoken for, so nothing is left to return.
    Livewire::actingAs($customer->user)
        ->test(ReturnCreate::class, ['order' => $order])
        ->assertOk()
        ->assertSee(__('shipping::returns.customer_nothing_returnable'));
});

test('unpaid orders and over-quantity requests are refused', function () {
    enableReturns();
    $customer = Customer::factory()->create();
    $product = returnableProduct();

    app(CartService::class)->add($product->id, 1);
    $unpaid = app(PlaceOrder::class)->handle([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'billing' => returnsBilling(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => returnsShippingMethod()->id,
    ]);

    expect(fn () => app(ReturnRequestService::class)->requestFromCustomer(
        $unpaid,
        [['order_item_id' => (int) $unpaid->items->firstOrFail()->id, 'quantity' => 1]],
        null,
        $customer,
    ))->toThrow(ValidationException::class);

    $staff = $this->createStaff();
    $order = paidReturnableOrder($customer, $product, 1, $staff);

    expect(fn () => app(ReturnRequestService::class)->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 5]],
        null,
        $customer,
    ))->toThrow(ValidationException::class);
});

test('staff approve receive and restock increments inventory', function () {
    enableReturns(withInventory: true);
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = returnableProduct(withInventory: true);

    app(ProductStock::class)->setQuantity($product, 5);

    $order = paidReturnableOrder($customer, $product, 2, $staff);
    expect(app(ProductStock::class)->quantityFor($product))->toBe(3);

    $returns = app(ReturnRequestService::class);
    $request = $returns->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 2]],
        'Damaged',
        $customer,
    );

    Livewire::actingAs($staff)
        ->test(ReturnShow::class, ['returnRequest' => $request])
        ->assertOk()
        ->call('approve')
        ->call('markReceived');

    $request = $request->fresh(['items']);
    expect($request->status)->toBe(ReturnRequestStatus::Received)
        ->and($request->approved_by)->toBe($staff->id)
        ->and($request->received_by)->toBe($staff->id);

    $itemId = (int) $request->items->firstOrFail()->id;

    Livewire::actingAs($staff)
        ->test(ReturnShow::class, ['returnRequest' => $request])
        ->set('restock_quantities', [$itemId => 2])
        ->call('restock')
        ->call('complete');

    $request = $request->fresh(['items']);

    expect(app(ProductStock::class)->quantityFor($product->fresh(['capabilities'])))->toBe(5)
        ->and($request->items->firstOrFail()->restocked_quantity)->toBe(2)
        ->and($request->restocked_at)->not->toBeNull()
        ->and($request->status)->toBe(ReturnRequestStatus::Completed);
});

test('restock is skipped gracefully when the inventory module is disabled', function () {
    enableReturns();
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = returnableProduct();

    $order = paidReturnableOrder($customer, $product, 1, $staff);
    $returns = app(ReturnRequestService::class);

    $request = $returns->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 1]],
        null,
        $customer,
    );
    $returns->markReceived($returns->approve($request));

    expect($returns->inventoryAvailable())->toBeFalse()
        ->and($returns->restock($request->fresh(['items']), [1 => 1]))->toBe(0);
});

test('staff can reject a return with a reason', function () {
    enableReturns();
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = returnableProduct();

    $order = paidReturnableOrder($customer, $product, 1, $staff);
    $request = app(ReturnRequestService::class)->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 1]],
        null,
        $customer,
    );

    Livewire::actingAs($staff)
        ->test(ReturnShow::class, ['returnRequest' => $request])
        ->set('reject_reason', 'Outside the return window')
        ->call('reject');

    $request = $request->fresh();
    expect($request->status)->toBe(ReturnRequestStatus::Rejected)
        ->and($request->staff_notes)->toBe('Outside the return window')
        ->and($request->rejected_by)->toBe($staff->id);

    // A rejected return is terminal.
    expect(fn () => app(ReturnRequestService::class)->approve($request))
        ->toThrow(ValidationException::class);
});

test('a customer cannot see or start a return on another customers order', function () {
    enableReturns();
    $staff = $this->createStaff();
    $owner = Customer::factory()->create();
    $other = Customer::factory()->create();
    $product = returnableProduct();

    $order = paidReturnableOrder($owner, $product, 1, $staff);
    app(ReturnRequestService::class)->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 1]],
        'Broken on arrival',
        $owner,
    );

    $this->actingAs($other->user)
        ->get(route('customer.returns'))
        ->assertOk()
        ->assertDontSee('Broken on arrival')
        ->assertDontSee($order->number);

    $this->actingAs($other->user)
        ->get(route('customer.returns.create', $order))
        ->assertNotFound();

    expect(fn () => app(ReturnRequestService::class)->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 1]],
        null,
        $other,
    ))->toThrow(ValidationException::class);
});

test('restocking a return never records a refund or credit note', function () {
    enableReturns(withInventory: true);
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = returnableProduct(withInventory: true);
    app(ProductStock::class)->setQuantity($product, 4);

    $order = paidReturnableOrder($customer, $product, 1, $staff);
    $returns = app(ReturnRequestService::class);

    $request = $returns->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 1]],
        null,
        $customer,
    );
    $request = $returns->markReceived($returns->approve($request));
    $returns->restock($request, [(int) $request->items->firstOrFail()->id => 1]);
    $returns->complete($request->fresh(['items']));

    expect(Refund::query()->count())->toBe(0)
        ->and(CreditNote::query()->count())->toBe(0)
        ->and($order->fresh(['payment'])->payment?->refundedAmount())->toBe(0)
        ->and($order->fresh()->status->value)->toBe('paid');
});

test('admin returns index and order detail section render the request', function () {
    enableReturns();
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = returnableProduct();

    $order = paidReturnableOrder($customer, $product, 1, $staff);
    $request = app(ReturnRequestService::class)->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 1]],
        'Faulty item',
        $customer,
    );

    $this->actingAs($staff)
        ->get(route('admin.shipping.returns'))
        ->assertOk()
        ->assertSee($order->number);

    $this->actingAs($staff)
        ->get(route('admin.shipping.returns.show', $request))
        ->assertOk()
        ->assertSee('Faulty item');

    $this->actingAs($staff)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee(route('admin.shipping.returns.show', $request), false);
});

test('return requests survive disabling the shipping module', function () {
    enableReturns();
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $product = returnableProduct();

    $order = paidReturnableOrder($customer, $product, 1, $staff);
    app(ReturnRequestService::class)->requestFromCustomer(
        $order,
        [['order_item_id' => (int) $order->items->firstOrFail()->id, 'quantity' => 1]],
        null,
        $customer,
    );

    app(ModuleManager::class)->disable('shipping');

    expect(app(ModuleManager::class)->isEnabled('shipping'))->toBeFalse()
        ->and(ReturnRequest::query()->where('order_id', $order->id)->exists())->toBeTrue();
});
