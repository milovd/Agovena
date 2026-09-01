<?php

declare(strict_types=1);

use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Extensions\ExtensionSettingDefinition;
use App\Agovena\Provisioning\Contracts\ConfiguresProvisionedProducts;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Enums\ProductOptionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('does not place encrypted option values in an order snapshot', function (): void {
    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'api_token',
        'label' => 'API token',
        'type' => ProductOptionType::Text,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);

    $pricer = app(ProductOptionPricer::class);
    $snapshot = $pricer->snapshot($product, ['api_token' => '[REDACTED]']);
    $item = OrderItem::factory()->create(['order_id' => Order::factory()->create()->id, 'product_id' => $product->id]);
    $pricer->storeRuntimeSecrets($item->id, $product, ['api_token' => '[REDACTED]']);

    expect($snapshot[0] ?? [])->not->toHaveKey('value_encrypted')
        ->and($pricer->runtimeValue($snapshot[0], $item->id))->toBe('[REDACTED]')
        ->and(DB::table('order_item_runtime_secrets')->where('order_item_id', $item->id)->value('value_encrypted'))
        ->not->toBe('[REDACTED]')
        ->and(Crypt::decryptString((string) DB::table('order_item_runtime_secrets')->where('order_item_id', $item->id)->value('value_encrypted')))
        ->toBe('"[REDACTED]"');
});

it('rejects inline encrypted option values without a runtime secret', function (): void {
    $pricer = app(ProductOptionPricer::class);
    $legacy = [
        'key' => 'api_token',
        'value' => '[REDACTED]',
        'value_encrypted' => Crypt::encryptString(json_encode('legacy-value', JSON_THROW_ON_ERROR)),
    ];

    expect(fn () => $pricer->runtimeValue($legacy, 999999))
        ->toThrow(InvalidArgumentException::class, 'runtime value is unavailable');
});

it('redacts provider-declared secret product options even without a secret-looking key', function (): void {
    $provider = Mockery::mock(Provisioner::class, ConfiguresProvisionedProducts::class);
    $provider->shouldReceive('id')->andReturn('secret-provider');
    $provider->shouldReceive('productSettings')->andReturn([
        new ExtensionSettingDefinition('x', 'Opaque setting', secret: true),
    ]);
    app(ProvisionerRegistry::class)->register($provider);

    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    $product->capabilities()->create([
        'capability' => 'provisionable',
        'config' => ['provider_key' => 'secret-provider'],
    ]);
    ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'x',
        'label' => 'Opaque setting',
        'type' => ProductOptionType::Text,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => Order::factory()->create()->id,
        'product_id' => $product->id,
    ]);
    $pricer = app(ProductOptionPricer::class);

    $snapshot = $pricer->snapshot($product, ['x' => 's3cr3t']);
    $pricer->storeRuntimeSecrets($item->id, $product, ['x' => 's3cr3t']);

    expect($snapshot[0]['value'] ?? null)->toBe('[REDACTED]')
        ->and($pricer->runtimeValue($snapshot[0], $item->id))->toBe('s3cr3t')
        ->and(DB::table('order_item_runtime_secrets')->where('order_item_id', $item->id)->where('key', 'x')->exists())
        ->toBeTrue();
});

it('redacts every option when its provisioner is unavailable', function (): void {
    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    $product->capabilities()->create([
        'capability' => 'provisionable',
        'config' => ['provider_key' => 'missing-secret-provider'],
    ]);
    ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'opaque_setting',
        'label' => 'Opaque setting',
        'type' => ProductOptionType::Text,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => Order::factory()->create()->id,
        'product_id' => $product->id,
    ]);
    $pricer = app(ProductOptionPricer::class);

    $snapshot = $pricer->snapshot($product, ['opaque_setting' => 's3cr3t']);
    $pricer->storeRuntimeSecrets($item->id, $product, ['opaque_setting' => 's3cr3t']);

    expect($snapshot[0]['value'] ?? null)->toBe('[REDACTED]')
        ->and($pricer->runtimeValue($snapshot[0], $item->id))->toBe('s3cr3t')
        ->and(DB::table('order_item_runtime_secrets')->where('order_item_id', $item->id)->where('key', 'opaque_setting')->exists())
        ->toBeTrue();
});

it('preserves embedded credentials from ordinary option keys in runtime secrets', function (): void {
    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'endpoint',
        'label' => 'Endpoint',
        'type' => ProductOptionType::Text,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);
    $item = OrderItem::factory()->create([
        'order_id' => Order::factory()->create()->id,
        'product_id' => $product->id,
    ]);
    $value = 'https://user:password@example.test/api';
    $pricer = app(ProductOptionPricer::class);
    $snapshot = $pricer->snapshot($product, ['endpoint' => $value]);
    $pricer->storeRuntimeSecrets($item->id, $product, ['endpoint' => $value]);

    expect($snapshot[0]['value'] ?? null)->toBe('[REDACTED]')
        ->and($pricer->runtimeValue($snapshot[0], $item->id))->toBe($value)
        ->and(DB::table('order_item_runtime_secrets')->where('order_item_id', $item->id)->where('key', 'endpoint')->exists())
        ->toBeTrue();
});
