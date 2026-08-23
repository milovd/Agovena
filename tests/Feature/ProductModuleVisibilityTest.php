<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Livewire\Admin\Products\Create;
use App\Livewire\Admin\Products\Edit;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('product create hides provisioning ui when provisioning module is disabled', function () {
    Livewire::actingAs($this->createStaff())
        ->test(Create::class)
        ->assertDontSee(__('admin.products.tabs.automation'))
        ->assertDontSee(__('admin.products.automation.enable_provisioning'))
        ->assertDontSee('wire:model="configureProvisioning"', false)
        ->assertDontSee('wire:model="provisioningServerId"', false)
        ->assertDontSee(__('admin.products.capabilities.provisionable'));
});

test('product edit hides provisioning ui when provisioning module is disabled', function () {
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->assertDontSee(__('admin.products.tabs.automation'))
        ->assertDontSee('wire:model="provisioningServerId"', false)
        ->assertDontSee('wire:model="capabilityEnabled.provisionable"', false)
        ->assertDontSee(__('admin.products.capabilities.provisionable'));
});

test('product edit hides provisioning ui when other capability modules are enabled', function () {
    enableFirstPartyModules(['digital-delivery', 'subscriptions']);

    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->assertSee(__('admin.products.tabs.automation'))
        ->assertSee(__('admin.products.capabilities.subscribable'))
        ->assertDontSee('wire:model="provisioningServerId"', false)
        ->assertDontSee('wire:model="capabilityEnabled.provisionable"', false)
        ->assertDontSee(__('admin.products.capabilities.provisionable'));
});

test('product edit hides events tab when events module is disabled', function () {
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->assertDontSee(__('admin.products.tabs.events'));

    expect(collect(app(AdminRegistrar::class)->productTabs())->pluck('id')->all())
        ->not->toContain('events');
});

test('product edit shows events tab when events module is enabled', function () {
    enableFirstPartyModules(['events']);
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->assertSee(__('admin.products.tabs.events'));

    expect(collect(app(AdminRegistrar::class)->productTabs())->pluck('id')->all())
        ->toContain('events');
});

test('product edit hides subscription fields when subscriptions module is disabled', function () {
    enableFirstPartyModules(['digital-delivery']);

    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->assertDontSee('wire:model="capabilityEnabled.subscribable"', false)
        ->assertDontSee('wire:model="subscriptionInterval"', false)
        ->assertDontSee('wire:model="subscriptionIntervalCount"', false)
        ->assertDontSee('wire:model="subscriptionTrialDays"', false);
});

test('digital and subscription capabilities can be combined and saved', function () {
    enableFirstPartyModules(['digital-delivery', 'subscriptions']);
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->call('applyPreset', 'digital')
        ->set('capabilityEnabled.subscribable', true)
        ->set('subscriptionInterval', 'month')
        ->set('subscriptionIntervalCount', 1)
        ->set('subscriptionTrialDays', 7)
        ->call('saveCapabilities')
        ->assertHasNoErrors();

    $product->refresh()->load('capabilities');

    expect($product->hasCapability('digital_secret'))->toBeTrue()
        ->and($product->hasCapability('subscribable'))->toBeTrue()
        ->and($product->capability('subscribable')?->config)->toMatchArray([
            'interval' => 'month',
            'interval_count' => 1,
            'trial_days' => 7,
        ]);
});

test('downloadable and subscription capabilities can be combined and saved', function () {
    enableFirstPartyModules(['digital', 'subscriptions']);
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->call('applyPreset', 'downloadable')
        ->set('capabilityEnabled.subscribable', true)
        ->set('subscriptionInterval', 'year')
        ->set('subscriptionIntervalCount', 1)
        ->call('saveCapabilities')
        ->assertHasNoErrors();

    $product->refresh()->load('capabilities');

    expect($product->hasCapability('digital'))->toBeTrue()
        ->and($product->hasCapability('subscribable'))->toBeTrue()
        ->and($product->capability('subscribable')?->config['interval'])->toBe('year');
});

test('physical and subscription capabilities can be combined and saved', function () {
    enableFirstPartyModules(['inventory', 'shipping', 'subscriptions']);
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->call('applyPreset', 'physical')
        ->set('capabilityEnabled.subscribable', true)
        ->set('subscriptionInterval', 'month')
        ->set('subscriptionIntervalCount', 3)
        ->call('saveCapabilities')
        ->assertHasNoErrors();

    $product->refresh()->load('capabilities');

    expect($product->hasCapability('physical'))->toBeTrue()
        ->and($product->hasCapability('inventory'))->toBeTrue()
        ->and($product->hasCapability('shippable'))->toBeTrue()
        ->and($product->hasCapability('subscribable'))->toBeTrue()
        ->and($product->capability('subscribable')?->config['interval_count'])->toBe(3);
});
