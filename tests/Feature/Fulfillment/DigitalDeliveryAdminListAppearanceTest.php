<?php

declare(strict_types=1);

use Agovena\Modules\DigitalDelivery\Http\Livewire\Admin\SecretsIndex;
use Agovena\Modules\DigitalDelivery\Models\DigitalSecretDelivery;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Lang;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('keys and codes show labelled mobile pool and delivery facts without secret values', function (): void {
    installAndEnableModule('digital-delivery');
    app(SyncRegisteredPermissions::class)(force: true);
    $viewer = $this->createStaff(permissions: ['digital_delivery.view']);
    $product = Product::factory()->active()->create(['name' => 'Licence product']);
    app(ProductCapabilityManager::class)->enable($product, 'digital_secret', ['source' => 'manual']);
    $order = Order::factory()->create();
    DigitalSecretDelivery::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_email' => 'customer@example.test',
        'source' => DigitalSecretDelivery::SOURCE_MANUAL,
        'status' => DigitalSecretDelivery::STATUS_DELIVERED,
        'value_hint' => '••••ABCD',
    ]);

    Livewire::actingAs($viewer)
        ->test(SecretsIndex::class)
        ->assertSee('ag-table--mobile-cards', false)
        ->assertSee('data-label="'.__('digital-delivery::admin.available').'"', false)
        ->assertSee('data-label="'.__('digital-delivery::admin.hint').'"', false)
        ->assertSee('ag-badge--success', false)
        ->assertSee('••••ABCD')
        ->assertDontSee('wire:submit="addCodes"', false)
        ->assertDontSee('wire:click="revoke(', false);

    $css = file_get_contents(resource_path('css/admin/components/_table.css'));
    expect($css)->toContain('.ag-table--mobile-cards tbody tr.ag-table__form-row td');
});

test('view-only staff cannot expose the manual assignment form by changing Livewire state', function (): void {
    installAndEnableModule('digital-delivery');
    app(SyncRegisteredPermissions::class)(force: true);
    $viewer = $this->createStaff(permissions: ['digital_delivery.view']);
    $product = Product::factory()->active()->create();
    $order = Order::factory()->create();
    $delivery = DigitalSecretDelivery::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_email' => 'customer@example.test',
        'source' => DigitalSecretDelivery::SOURCE_MANUAL,
        'status' => DigitalSecretDelivery::STATUS_PENDING_MANUAL,
    ]);

    Livewire::actingAs($viewer)
        ->test(SecretsIndex::class)
        ->set('assign_delivery_id', $delivery->id)
        ->assertDontSee('wire:submit="assign"', false)
        ->assertDontSee('wire:click="startAssign(', false);
});

test('managers can assign a pending delivery and must confirm revocation', function (string $locale): void {
    installAndEnableModule('digital-delivery');
    app(SyncRegisteredPermissions::class)(force: true);
    app()->setLocale($locale);
    $manager = $this->createStaff();
    $product = Product::factory()->active()->create();
    $order = Order::factory()->create(['number' => 'ORDER "A" & <priority>']);
    $pending = DigitalSecretDelivery::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_email' => 'customer@example.test',
        'source' => DigitalSecretDelivery::SOURCE_MANUAL,
        'status' => DigitalSecretDelivery::STATUS_PENDING_MANUAL,
    ]);
    DigitalSecretDelivery::query()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'customer_email' => 'customer@example.test',
        'source' => DigitalSecretDelivery::SOURCE_MANUAL,
        'status' => DigitalSecretDelivery::STATUS_DELIVERED,
        'value_hint' => '••••ABCD',
    ]);

    $confirmation = Lang::has('digital-delivery::admin.revoke_confirm')
        ? __('digital-delivery::admin.revoke_confirm', ['order' => $order->number])
        : __('common.confirm_consequential_action', ['action' => __('digital-delivery::admin.revoke'), 'name' => $order->number]);

    Livewire::actingAs($manager)
        ->test(SecretsIndex::class)
        ->assertSee('wire:confirm="'.e($confirmation).'"', false)
        ->call('startAssign', $pending->id)
        ->assertSee('wire:submit="assign"', false)
        ->assertSee('ag-table__form-row', false);
})->with(['en', 'nl']);

test('a rejected key pool label shows its validation error beside the field', function (): void {
    installAndEnableModule('digital-delivery');
    app(SyncRegisteredPermissions::class)(force: true);

    $html = Livewire::actingAs($this->createStaff())
        ->test(SecretsIndex::class)
        ->set('label', str_repeat('a', 121))
        ->call('addCodes')
        ->assertHasErrors(['label'])
        ->html();

    expect($html)->toMatch('/id="ds-label"(?:(?!<\/div>).)*class="ag-field__error"/s');
});
