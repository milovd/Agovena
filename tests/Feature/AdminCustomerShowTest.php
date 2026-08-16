<?php

declare(strict_types=1);

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Livewire\Admin\Customers\Show as AdminCustomerShow;
use App\Models\Customer;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin customer show renders with empty module capability sections', function () {
    $modules = app(ModuleManager::class);
    foreach (['digital', 'digital-delivery', 'events', 'subscriptions', 'provisioning'] as $id) {
        $modules->enable($id);
    }
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create([
        'name' => 'Empty Caps Customer',
        'email' => 'empty-caps@agovena.test',
    ]);

    Livewire::actingAs($this->createStaff())
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->assertOk()
        ->assertSee('Empty Caps Customer')
        ->call('selectPanel', 'capabilities')
        ->assertOk()
        ->assertSee(__('digital::admin.customer_empty'), false)
        ->assertSee(__('digital-delivery::admin.customer_empty'), false)
        ->assertSee(__('events::admin.no_customer_tickets'), false);
});

test('admin can update customer profile from show page', function () {
    $customer = Customer::factory()->create([
        'name' => 'Before Name',
        'email' => 'before@agovena.test',
    ]);
    $customer->user?->forceFill([
        'name' => 'Before Name',
        'email' => 'before@agovena.test',
    ])->save();

    Livewire::actingAs($this->createStaff())
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('selectPanel', 'profile')
        ->set('name', 'After Name')
        ->set('email', 'after@agovena.test')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->assertSee(__('admin.customers.profile_saved'), false);

    $customer->refresh();
    expect($customer->name)->toBe('After Name')
        ->and($customer->email)->toBe('after@agovena.test')
        ->and($customer->user?->name)->toBe('After Name')
        ->and($customer->user?->email)->toBe('after@agovena.test');
});
