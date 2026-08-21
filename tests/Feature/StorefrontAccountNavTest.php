<?php

declare(strict_types=1);

use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('logged out storefront header shows styled login and register actions', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('storefront.nav.login'), false)
        ->assertSee(__('storefront.nav.register'), false)
        ->assertSee('store-header__auth-link', false)
        ->assertSee('store-header__auth-register', false)
        ->assertSee('store-drawer__auth', false)
        ->assertDontSee('id="store-account-menu-button"', false);
});

test('logged in customer sees structured account menu without admin', function () {
    $user = User::factory()->create([
        'name' => 'Casey Customer',
        'email' => 'casey@agovena.test',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('id="store-account-menu-button"', false)
        ->assertSee('store-header__account-trigger', false)
        ->assertSee('store-account-menu__identity', false)
        ->assertSee('style="left: unset; right: 0;"', false)
        ->assertSee('Casey Customer', false)
        ->assertSee('casey@agovena.test', false)
        ->assertSee(__('storefront.nav.dashboard'), false)
        ->assertSee(__('storefront.nav.logout'), false)
        ->assertSee('store-account-menu__item--danger', false)
        ->assertDontSee(__('storefront.nav.admin'), false)
        ->assertDontSee(__('storefront.nav.login'), false);
});

test('logged in admin sees account menu with store administration link', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/')
        ->assertOk()
        ->assertSee('id="store-account-menu-button"', false)
        ->assertSee(__('storefront.nav.admin'), false)
        ->assertSee(route('admin.dashboard'), false)
        ->assertSee('store-account-menu__item--admin', false);
});

test('customer logout destination remains available from account menu', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee(route('customer.logout'), false);
});

test('footer uses configured store name and logo', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('footer-logo.png')->store('branding', 'public');
    app(SettingsRepository::class)->set('general', 'site_name', 'Northwind Market');
    app(SettingsRepository::class)->set('branding', 'logo_path', $path);

    $this->get('/')
        ->assertOk()
        ->assertSee('Northwind Market', false)
        ->assertSee('store-footer__logo', false)
        ->assertSee('/storage/'.$path, false);
});

test('authenticated footer lists module account destinations when enabled', function () {
    app(ModuleManager::class)->enable('digital');
    app(SyncRegisteredPermissions::class)(force: true);

    $user = User::factory()->create();

    expect(collect(app(CustomerAccountNav::class)->items())->pluck('id'))
        ->toContain('digital-downloads');

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee(route('customer.account'), false)
        ->assertSee(route('customer.orders.index'), false)
        ->assertSee(route('customer.downloads'), false)
        ->assertSee(__('digital::customer.nav'), false);
});

test('guest footer offers sign in instead of private account destinations', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('storefront.nav.login'), false)
        ->assertSee(__('storefront.nav.register'), false)
        ->assertDontSee(route('customer.orders.index'), false);
});
