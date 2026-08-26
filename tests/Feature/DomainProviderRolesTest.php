<?php

declare(strict_types=1);

use Agovena\Modules\Domains\Contracts\DomainDnsProvider;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainService;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

it('keeps DNS provider registration independent from registrar registration', function (): void {
    installAndEnableModules(['domains']);

    $provider = Mockery::mock(DomainDnsProvider::class);
    $provider->shouldReceive('key')->andReturn('cloudflare-dns');
    $provider->shouldReceive('capabilities')->andReturn(['zone_management', 'record_management']);

    $registry = app(DomainDnsProviderRegistry::class);
    $registry->register($provider);

    expect($registry->has('cloudflare-dns'))->toBeTrue()
        ->and($registry->get('cloudflare-dns'))->toBe($provider)
        ->and($registry->get('namecheap-registrar'))->toBeNull();
});

it('snapshots registrar and DNS provider roles separately on a domain registration', function (): void {
    installAndEnableModules(['domains']);

    $customer = Customer::factory()->create([
        'name' => 'Domain Buyer',
        'email' => 'domain-buyer@example.test',
    ]);
    $product = Product::factory()->active()->create([
        'name' => 'Domain registration',
        'price_amount' => 1200,
    ]);
    app(ProductCapabilityManager::class)->enable($product, 'domain_registration', [
        'registrar_key' => 'namecheap-registrar',
        'dns_provider_key' => 'cloudflare-dns',
        'domain_name' => 'Example.test',
    ]);

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
        'unit_amount' => 1200,
        'line_total_amount' => 1200,
        'currency' => 'EUR',
    ]);
    $order->setRelation('items', collect([$item]));

    app(DomainService::class)->createFromPaidOrder($order);
    $registration = DomainRegistration::query()->firstOrFail();

    expect($registration->registrar_key)->toBe('namecheap-registrar')
        ->and($registration->provider_key)->toBe('namecheap-registrar')
        ->and($registration->dns_provider_key)->toBe('cloudflare-dns')
        ->and($registration->meta['registrar_key'] ?? null)->toBe('namecheap-registrar')
        ->and($registration->meta['dns_provider_key'] ?? null)->toBe('cloudflare-dns');
});
