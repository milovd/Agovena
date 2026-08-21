<?php

declare(strict_types=1);

use App\Agovena\Customer\AccountNavItem;
use App\Agovena\Customer\AddressData;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Customer\SaveCustomerAddress;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Livewire\Customer\Account\Addresses;
use App\Livewire\Customer\Account\Dashboard;
use App\Livewire\Customer\Account\Profile;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Livewire\Livewire;

test('customer can save and delete an address', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer->user)
        ->test(Addresses::class)
        ->set('name', 'Ada Lovelace')
        ->set('line1', 'Analytical Engine 1')
        ->set('city', 'London')
        ->set('postal_code', 'SW1A 1AA')
        ->set('country', 'GB')
        ->set('is_default_billing', true)
        ->call('save')
        ->assertHasNoErrors();

    $address = CustomerAddress::query()->where('customer_id', $customer->id)->first();

    expect($address)->not->toBeNull()
        ->and($address->line1)->toBe('Analytical Engine 1')
        ->and($address->is_default_billing)->toBeTrue();

    Livewire::actingAs($customer->user)
        ->test(Addresses::class)
        ->call('delete', $address->id)
        ->assertHasNoErrors();

    expect(CustomerAddress::query()->whereKey($address->id)->exists())->toBeFalse();
});

test('account sidebar nests purchases support and account groups with icons', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer->user)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('customer.account.nav_group_purchases'), false)
        ->assertSee(__('customer.account.nav_orders'), false)
        ->assertSee(__('customer.account.nav_invoices'), false)
        ->assertSee(__('customer.account.nav_tickets'), false)
        ->assertDontSee(__('customer.account.nav_group_services'), false)
        ->assertSee('store-account__link-icon', false)
        ->assertSee(__('customer.account.nav_group_account'), false)
        ->assertSee(__('customer.account.nav_settings'), false)
        ->assertSee(__('customer.account.nav_credits'), false)
        ->assertDontSee('/account/api-tokens', false)
        ->assertDontSee(route('customer.addresses'), false);
});

test('account sidebar nests services downloads under services when modules are enabled', function () {
    app(ModuleManager::class)->enable('digital');
    app(ModuleManager::class)->enable('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create();
    $items = collect(app(CustomerAccountNav::class)->items());

    expect($items->firstWhere('id', 'digital-downloads')?->group)
        ->toBe(AccountNavItem::GROUP_SERVICES)
        ->and($items->firstWhere('id', 'services')?->group)
        ->toBe(AccountNavItem::GROUP_SERVICES);

    Livewire::actingAs($customer->user)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('customer.account.nav_group_services'), false)
        ->assertSee(__('digital::customer.nav'), false)
        ->assertSee(__('provisioning::customer.nav'), false);
});

test('account sidebar nests returns under purchases when shipping module is enabled', function () {
    app(ModuleManager::class)->enable('shipping');
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create();
    $item = collect(app(CustomerAccountNav::class)->items())
        ->firstWhere('id', 'shipping-returns');

    expect($item)->not->toBeNull()
        ->and($item->group)->toBe(AccountNavItem::GROUP_PURCHASES);

    Livewire::actingAs($customer->user)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('shipping::returns.customer_nav'), false)
        ->assertSee(route('customer.returns'), false);
});

test('account sidebar nests subscriptions under account when module is enabled', function () {
    app(ModuleManager::class)->enable('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create();
    $item = collect(app(CustomerAccountNav::class)->items())
        ->firstWhere('id', 'subscriptions');

    expect($item)->not->toBeNull()
        ->and($item->group)->toBe(AccountNavItem::GROUP_ACCOUNT);

    Livewire::actingAs($customer->user)
        ->test(Dashboard::class)
        ->assertOk()
        ->assertSee(__('subscriptions::customer.nav'), false)
        ->assertSee(route('customer.subscriptions'), false);
});

test('storefront account has no api token management', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($customer->user)
        ->get('/account/api-tokens')
        ->assertNotFound();
});

test('profile settings includes address management', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer->user)
        ->test(Profile::class)
        ->assertOk()
        ->assertSee(__('customer.addresses.heading'), false)
        ->assertSee(__('customer.addresses.add_heading'), false);
});

test('setting a default billing address clears previous default', function () {
    $customer = Customer::factory()->create();
    $first = app(SaveCustomerAddress::class)->handle(
        $customer,
        AddressData::fromArray([
            'name' => 'First',
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
        ['is_default_billing' => true],
    );

    $second = app(SaveCustomerAddress::class)->handle(
        $customer,
        AddressData::fromArray([
            'name' => 'Second',
            'line1' => 'Street 2',
            'city' => 'Rotterdam',
            'postal_code' => '3000 BB',
            'country' => 'NL',
        ]),
        ['is_default_billing' => true],
    );

    expect($first->fresh()->is_default_billing)->toBeFalse()
        ->and($second->fresh()->is_default_billing)->toBeTrue();
});
