<?php

declare(strict_types=1);

use Agovena\Modules\Digital\Models\DigitalAsset;
use Agovena\Modules\Digital\Models\DigitalEntitlement;
use Agovena\Modules\Events\Enums\EventStatus;
use Agovena\Modules\Events\Enums\EventTicketStatus;
use Agovena\Modules\Events\EventService;
use Agovena\Modules\Events\Models\Event;
use Agovena\Modules\Events\Models\EventPerformance;
use Agovena\Modules\Events\Models\EventTicket;
use Agovena\Modules\Events\Models\EventTicketType;
use Agovena\Modules\Inventory\InventoryService;
use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Store\ApplyStorePresets;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function enableEventsModule(): void
{
    app(ModuleManager::class)->enable('events');
    app(SyncRegisteredPermissions::class)(force: true);
}

function billingForEvents(): AddressData
{
    return AddressData::fromArray([
        'name' => 'Ticket Buyer',
        'line1' => 'Street 1',
        'city' => 'Amsterdam',
        'postal_code' => '1000 AA',
        'country' => 'NL',
    ]);
}

/**
 * @return array{event: Event, performance: EventPerformance, type: EventTicketType, product: Product}
 */
function makePublishedTicketProduct(int $capacity = 4): array
{
    $event = Event::query()->create([
        'name' => 'Spring Concert',
        'slug' => 'spring-concert-'.Str::lower(Str::random(4)),
        'venue' => 'Stadsschouwburg',
        'status' => EventStatus::Published,
    ]);
    $performance = EventPerformance::query()->create([
        'event_id' => $event->id,
        'starts_at' => now()->addWeek(),
        'capacity' => $capacity,
        'venue' => $event->venue,
    ]);
    $product = Product::factory()->active()->create([
        'name' => 'Stalls ticket',
        'price_amount' => 3500,
    ]);
    app(ProductCapabilityManager::class)->enable($product, 'event_ticket');
    $type = EventTicketType::query()->create([
        'event_id' => $event->id,
        'performance_id' => $performance->id,
        'product_id' => $product->id,
        'name' => 'Stalls',
    ]);

    return compact('event', 'performance', 'type', 'product');
}

test('events module registers capability and hides account nav until tickets exist', function () {
    $customer = Customer::factory()->create();
    expect(app(ProductCapabilityRegistry::class)->has('event_ticket'))->toBeFalse();

    enableEventsModule();

    expect(app(ProductCapabilityRegistry::class)->has('event_ticket'))->toBeTrue();

    $this->actingAs($customer->user);
    expect(collect(app(CustomerAccountNav::class)->items())->pluck('id')->all())
        ->not->toContain('event-tickets');
});

test('paid event order issues unique tickets and check-in is idempotent', function () {
    enableEventsModule();
    $customer = Customer::factory()->create();
    $setup = makePublishedTicketProduct(capacity: 10);

    app(CartService::class)->add($setup['product']->id, 2);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForEvents(),
    ]);

    expect(EventTicket::query()->where('order_id', $order->id)->count())->toBe(0);

    app(RecordManualPayment::class)->handle($order, $this->createStaff(), 'TCK-PAY-1');

    $tickets = EventTicket::query()->where('order_id', $order->id)->get();
    expect($tickets)->toHaveCount(2)
        ->and($tickets->pluck('token')->unique())->toHaveCount(2)
        ->and($tickets->every(fn (EventTicket $ticket): bool => strlen($ticket->token) === 64))->toBeTrue();

    $this->actingAs($customer->user);
    expect(collect(app(CustomerAccountNav::class)->items())->pluck('id')->all())
        ->toContain('event-tickets');

    $first = app(EventService::class)->checkIn($tickets[0]->token, $this->createStaff());
    $again = app(EventService::class)->checkIn($tickets[0]->number, $this->createStaff());

    expect($first['already'])->toBeFalse()
        ->and($again['already'])->toBeTrue()
        ->and($again['ticket']->checked_in_at?->equalTo($first['ticket']->checked_in_at))->toBeTrue()
        ->and($tickets[0]->fresh()?->status)->toBe(EventTicketStatus::CheckedIn);
});

test('event capacity is enforced before the order is placed', function () {
    enableEventsModule();
    $setup = makePublishedTicketProduct(capacity: 1);
    app(CartService::class)->add($setup['product']->id, 2);

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => 'Sold Out',
        'customer_email' => 'soldout@example.test',
        'billing' => billingForEvents(),
    ]))->toThrow(ValidationException::class);
});

test('mixed cart of ticket shirt and digital programme shares one order and invoice', function () {
    $modules = app(ModuleManager::class);
    foreach (['inventory', 'shipping', 'digital', 'events'] as $id) {
        $modules->enable($id);
    }
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create();
    $capabilities = app(ProductCapabilityManager::class);

    $setup = makePublishedTicketProduct(capacity: 20);

    $shirt = Product::factory()->active()->create(['name' => 'Tour shirt', 'price_amount' => 2200]);
    $capabilities->enable($shirt, 'physical');
    $capabilities->enable($shirt, 'inventory');
    $capabilities->enable($shirt, 'shippable', ['weight_grams' => 200]);
    app(InventoryService::class)->setQuantity($shirt, 5);

    $programme = Product::factory()->active()->create(['name' => 'Digital programme', 'price_amount' => 800]);
    $capabilities->enable($programme, 'digital');
    Storage::fake('local');
    $path = 'digital/'.$programme->id.'/programme.txt';
    Storage::disk('local')->put($path, 'programme');
    DigitalAsset::query()->create([
        'product_id' => $programme->id,
        'label' => 'Programme',
        'disk' => 'local',
        'path' => $path,
        'filename' => 'programme.txt',
        'download_limit' => 3,
        'is_active' => true,
    ]);

    $method = ShippingMethod::query()->create([
        'name' => 'Parcel',
        'code' => 'event-parcel',
        'type' => ShippingMethodType::Flat,
        'zone_id' => null,
        'config' => ['amount' => 495],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 10,
    ]);

    $cart = app(CartService::class);
    $cart->add($setup['product']->id, 1);
    $cart->add($shirt->id, 1);
    $cart->add($programme->id, 1);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => billingForEvents(),
        'shipping_same_as_billing' => true,
        'shipping_method_id' => $method->id,
    ]);

    expect($order->items)->toHaveCount(3)
        ->and(Invoice::query()->where('order_id', $order->id)->exists())->toBeTrue();

    app(RecordManualPayment::class)->handle($order, $this->createStaff(), 'EVT-MIX-1');

    expect(EventTicket::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(DigitalEntitlement::query()->where('order_id', $order->id)->exists())->toBeTrue()
        ->and(Invoice::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('events store preset enables the events module without disabling others', function () {
    app(ModuleManager::class)->enable('digital');
    $enabled = app(ApplyStorePresets::class)->handle(['events']);

    expect($enabled)->toContain('events')
        ->and(app(ModuleManager::class)->isEnabled('events'))->toBeTrue()
        ->and(app(ModuleManager::class)->isEnabled('digital'))->toBeTrue();
});
