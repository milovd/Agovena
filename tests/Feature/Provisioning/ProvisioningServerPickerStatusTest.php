<?php

declare(strict_types=1);

use App\Livewire\Admin\Products\Create;
use App\Livewire\Admin\Products\Edit;
use App\Models\Product;
use App\Models\ProvisioningServer;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function provisioningServersWithMixedConfiguration(): array
{
    installAndEnableModule('provisioning');
    installAndEnableExtension('pterodactyl');

    $ready = ProvisioningServer::query()->create([
        'name' => 'Ready panel',
        'provider_key' => 'pterodactyl',
        'settings' => [
            'panel_url' => 'https://panel.example.test',
            'application_api_key' => 'ptla_ready_not_real',
            'user_id' => '1',
        ],
        'is_active' => true,
    ]);
    $incomplete = ProvisioningServer::query()->create([
        'name' => 'Incomplete panel',
        'provider_key' => 'pterodactyl',
        'settings' => [
            'panel_url' => 'https://panel.example.test',
            'application_api_key' => '',
            'user_id' => '',
        ],
        'is_active' => true,
    ]);

    return [$ready, $incomplete];
}

test('the product edit form labels provisioning servers that are not configured', function () {
    [$ready, $incomplete] = provisioningServersWithMixedConfiguration();
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->set('capabilityEnabled.provisionable', true)
        ->assertSee(__('admin.provider_status.option_not_configured', ['label' => 'Incomplete panel - pterodactyl']))
        ->assertDontSee(__('admin.provider_status.option_not_configured', ['label' => 'Ready panel - pterodactyl']))
        ->assertSee(__('admin.provider_status.picker_hint'))
        ->assertSee(route('admin.provisioning.servers'), false)
        ->assertDontSee('ptla_ready_not_real');
});

test('the product create form labels provisioning servers that are not configured', function () {
    provisioningServersWithMixedConfiguration();

    Livewire::actingAs($this->createStaff())
        ->test(Create::class)
        ->set('configureProvisioning', true)
        ->assertSee(__('admin.provider_status.option_not_configured', ['label' => 'Incomplete panel']))
        ->assertDontSee(__('admin.provider_status.option_not_configured', ['label' => 'Ready panel']))
        ->assertSee(__('admin.provider_status.picker_hint'));
});

test('the product form hides the not configured hint when every server is configured', function () {
    [, $incomplete] = provisioningServersWithMixedConfiguration();
    $incomplete->delete();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => Product::factory()->create()])
        ->set('capabilityEnabled.provisionable', true)
        ->assertDontSee(__('admin.provider_status.picker_hint'));
});

test('selecting a server that is not configured keeps the current saving behaviour', function () {
    [, $incomplete] = provisioningServersWithMixedConfiguration();
    $product = Product::factory()->create();

    Livewire::actingAs($this->createStaff())
        ->test(Edit::class, ['product' => $product])
        ->set('capabilityEnabled.provisionable', true)
        ->set('provisioningServerId', $incomplete->id)
        ->set('providerSettings.location_id', '1')
        ->set('providerSettings.nest_id', '1')
        ->set('providerSettings.egg_id', '1')
        ->call('saveCapabilities')
        ->assertHasNoErrors(['provisioningServerId']);

    expect($product->refresh()->capability('provisionable')?->config['server_id'] ?? null)->toBe($incomplete->id);
});
