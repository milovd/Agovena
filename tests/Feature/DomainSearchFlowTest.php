<?php

declare(strict_types=1);

use Agovena\Modules\Domains\DomainSearchService;
use Agovena\Modules\Domains\DomainService;
use Agovena\Modules\Domains\Enums\DomainRegistrationStatus;
use Agovena\Modules\Domains\Http\Livewire\Storefront\DomainSearch;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Cart\CartService;
use App\Agovena\Cart\PricedCartLine;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Money\Money;
use App\Enums\ProductOptionType;
use App\Events\OrderPreflight;
use App\Livewire\Storefront\ProductShow;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOption;
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
    ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'domain_name',
        'label' => 'Domain name',
        'type' => ProductOptionType::Text,
        'is_required' => true,
        'is_active' => true,
        'sort' => 0,
        'price_adjustment_amount' => 0,
        'constraints' => ['minlength' => 4],
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

it('shows provider-specific prices for each available extension', function (): void {
    createDemoDomainProduct();

    $result = app(DomainSearchService::class)->search('agovena.com');
    $alternatives = collect($result['alternatives'])->keyBy('domain');

    expect($alternatives['agovena.test']['price_minor'])->toBe(1299)
        ->and($alternatives['agovena.invalid']['price_minor'])->toBe(999)
        ->and($alternatives['agovena.invalid']['price'])->not->toBe($alternatives['agovena.test']['price']);
});

it('keeps domain product pages simple and sends domain selection to configuration', function (): void {
    $product = createDemoDomainProduct();

    $this->get(route('storefront.product', $product->slug))
        ->assertOk()
        ->assertSee(__('storefront.product.domain_price_dynamic'), false)
        ->assertSee(__('storefront.product.add_to_cart'), false)
        ->assertDontSee(__('domains::storefront.product_title'), false)
        ->assertDontSee(__('storefront.product.delivery_title'), false)
        ->assertDontSee(__('storefront.product.returns_title'), false)
        ->assertDontSee('id="domain-query"', false)
        ->assertSee('wire:submit="addToCart"', false);

    Livewire::test(ProductShow::class, ['slug' => $product->slug])
        ->call('buyNow')
        ->assertRedirect(route('domains.product.configure', [
            'slug' => $product->slug,
            'intent' => 'checkout',
            'quantity' => 1,
        ]));

    $this->get(route('domains.product.configure', $product->slug))
        ->assertOk()
        ->assertSee(__('domains::storefront.configuration_title', ['product' => $product->name]), false)
        ->assertSee('<nav class="store-breadcrumbs store-breadcrumbs--compact"', false)
        ->assertSee(__('storefront.product.configure_breadcrumb'), false)
        ->assertDontSee('store-domain-search__back', false)
        ->assertSee('id="domain-query"', false);
});

it('scopes domain configuration and selection to the product page product', function (): void {
    $product = createDemoDomainProduct();

    $component = Livewire::test(DomainSearch::class, ['productId' => $product->id])
        ->set('query', 'agovena.com')
        ->call('search');
    $result = $component->get('result');
    $token = collect($result['alternatives'])->firstWhere('domain', 'agovena.test')['selection_token'];

    $component->call('selectDomain', $token)
        ->assertRedirect(route('storefront.cart'));

    expect(collect(session('domains.quotes'))
        ->contains(fn (array $quote): bool => (int) ($quote['product_id'] ?? 0) === $product->id))
        ->toBeTrue();
});

it('preserves buy now intent and shows continue in domain configuration', function (): void {
    $product = createDemoDomainProduct();

    $component = Livewire::withQueryParams(['intent' => 'checkout'])
        ->test(DomainSearch::class, ['productId' => $product->id])
        ->set('query', 'agovena.com')
        ->call('search')
        ->assertSee(__('domains::storefront.continue'), false)
        ->assertDontSee(__('domains::storefront.select'), false);

    $result = $component->get('result');
    $token = collect($result['alternatives'])->firstWhere('domain', 'agovena.test')['selection_token'];

    $component->call('selectDomain', $token)
        ->assertRedirect(route('storefront.checkout'));
});

it('uses the selected extension quote in cart pricing', function (): void {
    $product = createDemoDomainProduct();

    $component = Livewire::test(DomainSearch::class, ['productId' => $product->id])
        ->set('query', 'agovena.com')
        ->call('search');
    $result = $component->get('result');
    $token = collect($result['alternatives'])->firstWhere('domain', 'agovena.invalid')['selection_token'];

    $component->call('selectDomain', $token);
    $line = app(CartService::class)->pricedLines()[0];

    expect($line->unitPrice->amount)->toBe(999)
        ->and($line->unitPrice->currency)->toBe('EUR')
        ->and(collect(session('domains.quotes'))->firstWhere('domain', 'agovena.invalid')['price_minor'])->toBe(999);
});

it('rejects checkout when a domain quote price changed', function (): void {
    $product = createDemoDomainProduct();
    $result = app(DomainSearchService::class)->search('agovena.com', $product->id);
    $token = app(DomainSearchService::class)->issueSelection(
        collect($result['alternatives'])->firstWhere('domain', 'agovena.invalid'),
        $product->id,
    );
    session()->put('domains.quotes.'.$token.'.price_minor', 1299);

    $line = new PricedCartLine(
        productId: $product->id,
        label: $product->name,
        quantity: 1,
        unitPrice: Money::of(1299, 'EUR'),
        lineTotal: Money::of(1299, 'EUR'),
        selections: ['domain_name' => 'agovena.invalid'],
    );

    expect(fn () => event(new OrderPreflight([$line])))
        ->toThrow(ValidationException::class, __('domains::storefront.validation.price_changed', ['domain' => 'agovena.invalid']));
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
