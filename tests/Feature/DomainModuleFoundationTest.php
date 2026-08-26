<?php

declare(strict_types=1);

use Agovena\Modules\Domains\DomainService;
use Agovena\Modules\Domains\Enums\DomainRegistrationStatus;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Modules\ModuleManager;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\File;

it('discovers the domains module from the optional package catalog', function (): void {
    $root = config('agovena.packages.optional_packages_path');
    $catalog = config('agovena.packages.monorepo.packages');
    $discovered = app(ModuleManager::class)->discover();
    $ids = array_map(static fn ($manifest): string => $manifest->id, $discovered);

    expect($catalog['domains'] ?? null)->toBe([
        'kind' => 'module',
        'path' => 'modules/domains',
    ])
        ->and(File::exists($root.'/modules/domains/module.json'))->toBeTrue()
        ->and($ids)->toContain('domains');
});

it('creates an idempotent pending domain registration from a paid order item', function (): void {
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
        'provider_key' => 'cloudflare-registrar',
        'domain_name' => 'Example.test',
        'auto_renew' => true,
        'provider_settings' => ['years' => 1],
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
    app(DomainService::class)->createFromPaidOrder($order);

    $registration = DomainRegistration::query()->first();

    expect($registration)->not->toBeNull()
        ->number->toStartWith('DOM-')
        ->domain_name->toBe('example.test')
        ->provider_key->toBe('cloudflare-registrar')
        ->status->toBe(DomainRegistrationStatus::Pending)
        ->auto_renew->toBeTrue();

    expect($registration->meta['awaiting_domain_name'] ?? null)->toBeFalse()
        ->and(DomainRegistration::query()->count())->toBe(1);
});
