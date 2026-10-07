<?php

declare(strict_types=1);

use Agovena\Extensions\Provisioning\Pterodactyl\PterodactylApi;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Livewire\Admin\Products\Edit as EditProductForm;
use App\Models\Product;
use App\Models\ProvisioningServer;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\Support\FakePterodactylApi;

uses(CreatesStaff::class);

function readinessPterodactyl(bool $configured): void
{
    app(ModuleManager::class)->discover();
    app(ExtensionManager::class)->discover();
    app()->instance(PterodactylApi::class, new FakePterodactylApi);
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    installAndEnableExtension('pterodactyl');

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('pterodactyl', 'panel_url', 'https://panel.example.test');
    $settings->set('pterodactyl', 'user_id', '1');
    if ($configured) {
        $settings->set('pterodactyl', 'application_api_key', '[REDACTED]', secret: true);
    }
}

/** @param array<string, mixed> $extra */
function readinessProduct(array $extra = []): Product
{
    $product = Product::factory()->active()->create(['price_amount' => 5000, 'slug' => 'readiness-game-server']);
    app(ProductCapabilityManager::class)->enable($product, 'provisionable', array_merge([
        'provider_key' => 'pterodactyl',
        'provider_settings' => ['location_id' => '1', 'nest_id' => '1', 'egg_id' => '15', 'memory' => '1024', 'disk' => '2048'],
    ], $extra));

    return $product->fresh(['capabilities']);
}

test('a configured provider keeps a provisionable product orderable', function () {
    readinessPterodactyl(configured: true);
    $product = readinessProduct();

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee(__('storefront.product.add_to_cart'))
        ->assertDontSee(__('storefront.product.unavailable_to_order'));

    app(CartService::class)->add($product->id, 1);
    expect(app(CartService::class)->lines())->toHaveCount(1);
});

test('missing provider credentials keep the product visible but not orderable', function () {
    readinessPterodactyl(configured: false);
    $product = readinessProduct();

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee($product->name)
        ->assertSee(__('storefront.product.unavailable_to_order'))
        ->assertDontSee(__('storefront.product.add_to_cart'));

    expect(fn () => app(CartService::class)->add($product->id, 1))->toThrow(ValidationException::class)
        ->and(app(CartService::class)->lines())->toBe([]);

    $this->get('/products/'.$product->slug.'/configure')->assertRedirect('/products/'.$product->slug);
});

test('a selected provisioning server without required connection fields is not orderable', function () {
    readinessPterodactyl(configured: true);
    $server = ProvisioningServer::query()->create([
        'name' => 'Incomplete panel',
        'provider_key' => 'pterodactyl',
        'settings' => ['panel_url' => 'https://panel.example.test', 'user_id' => '1'],
        'is_active' => true,
    ]);
    $product = readinessProduct(['server_id' => $server->id]);

    expect(fn () => app(CartService::class)->add($product->id, 1))->toThrow(ValidationException::class);

    $server->update(['settings' => [
        'panel_url' => 'https://panel.example.test',
        'application_api_key' => '[REDACTED]',
        'user_id' => '1',
    ]]);
    app(CartService::class)->add($product->id, 1);
    expect(app(CartService::class)->lines())->toHaveCount(1);

    $server->update(['is_active' => false]);
    expect(fn () => app(CartService::class)->add($product->id, 1))->toThrow(ValidationException::class);
});

test('a disabled provider extension keeps the product page reachable but not orderable', function () {
    readinessPterodactyl(configured: true);
    $product = readinessProduct();
    app(ExtensionManager::class)->disable('pterodactyl');
    app(ExtensionManager::class)->rebuildRuntime();

    $this->get('/products/'.$product->slug)
        ->assertOk()
        ->assertSee(__('storefront.product.unavailable_to_order'));

    expect(fn () => app(CartService::class)->add($product->id, 1))->toThrow(ValidationException::class);
});

test('admin product editor warns when an active product cannot be ordered', function () {
    readinessPterodactyl(configured: false);
    $product = readinessProduct();

    Livewire::actingAs($this->createStaff())
        ->test(EditProductForm::class, ['product' => $product])
        ->assertSee(__('admin.products.form.not_orderable'));

    app(ExtensionSettingsRepository::class)->set('pterodactyl', 'application_api_key', '[REDACTED]', secret: true);

    Livewire::actingAs($this->createStaff())
        ->test(EditProductForm::class, ['product' => $product])
        ->assertDontSee(__('admin.products.form.not_orderable'));
});
