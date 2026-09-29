<?php

declare(strict_types=1);

use Agovena\Modules\Domains\DomainSearchService;
use Agovena\Modules\Domains\DomainService;
use Agovena\Modules\Domains\Enums\DomainRegistrationStatus;
use Agovena\Modules\Domains\Http\Livewire\Storefront\DomainSearch;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Cart\PricedCartLine;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Money\Money;
use App\Events\OrderPreflight;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function createDemoDomainProduct(): Product
{
    $product = Product::factory()->active()->create([
        'name' => 'Demo domain registration',
        'price_amount' => 1299,
        'currency' => 'EUR',
    ]);
    app(ProductCapabilityManager::class)->enable($product, 'domain_registration', [
        'provider_key' => 'demo-registrar',
        'registrar_key' => 'demo-registrar',
        'dns_provider_key' => 'demo-dns',
        'allowed_tlds' => ['test', 'invalid'],
        'mode' => 'demo',
        'calls_enabled' => false,
    ]);

    return $product->fresh();
}

beforeEach(function (): void {
    installAndEnableModule('domains');
});

it('shows agovena.com as unavailable with reserved demo alternatives', function (): void {
    createDemoDomainProduct();

    $result = app(DomainSearchService::class)->search('https://www.Agovena.com/path');

    expect($result['requested'])->toMatchArray([
        'domain' => 'agovena.com',
        'available' => false,
        'reason' => 'registered',
    ])
        ->and(collect($result['alternatives'])->pluck('domain')->all())
        ->toContain('agovena.test', 'agovena.invalid');
});

it('selects a searched domain into checkout without using a product detail page', function (): void {
    createDemoDomainProduct();

    $component = Livewire::test(DomainSearch::class)
        ->set('query', 'agovena.com')
        ->call('search');
    $result = $component->get('result');
    $token = collect($result['alternatives'])->firstWhere('domain', 'agovena.test')['selection_token'];

    $component->call('selectDomain', $token)
        ->assertRedirect(route('storefront.checkout'));

    expect(session('domains.quotes'))->not->toBeEmpty();
});

it('blocks a domain line at order preflight when it was not selected through search', function (): void {
    $product = createDemoDomainProduct();
    $line = new PricedCartLine(
        productId: $product->id,
        label: $product->name,
        quantity: 1,
        unitPrice: Money::of(1299, 'EUR'),
        lineTotal: Money::of(1299, 'EUR'),
        selections: ['domain_name' => 'agovena.test'],
    );

    expect(fn () => event(new OrderPreflight([$line])))
        ->toThrow(ValidationException::class);
});

it('fulfills a paid demo domain and creates an isolated DNS zone', function (): void {
    $product = createDemoDomainProduct();
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_name' => $customer->name,
        'status' => 'paid',
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'label' => $product->name,
        'quantity' => 1,
        'unit_amount' => 1299,
        'line_total_amount' => 1299,
        'currency' => 'EUR',
        'options_snapshot' => [
            ['key' => 'domain_name', 'label' => 'Domain name', 'value' => 'agovena.test', 'display' => 'agovena.test'],
        ],
    ]);
    $order->setRelation('items', collect([$item]));

    app(DomainService::class)->createFromPaidOrder($order);
    $registration = DomainRegistration::query()->firstOrFail();
    $registration = app(DomainService::class)->register($registration);
    $registration = app(DomainService::class)->ensureDnsZone($registration);

    expect($registration->status)->toBe(DomainRegistrationStatus::Active)
        ->and($registration->provider_key)->toBe('demo-registrar')
        ->and(data_get($registration->meta, 'dns_zone.zone_reference'))->toStartWith('demo-zone-')
        ->and(app(DomainService::class)->dnsRecords($registration))->not->toBeEmpty();
});
