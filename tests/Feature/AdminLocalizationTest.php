<?php

use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\Roles\Index;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/**
 * Admin chrome, settings, dashboard, appearance and content must render
 * translations — never the raw translation keys they are registered with.
 *
 * @return list<string>
 */
function adminLocalizedRoutes(): array
{
    return [
        '/admin',
        '/admin/settings',
        '/admin/settings/general',
        '/admin/settings/branding',
        '/admin/settings/store',
        '/admin/settings/mail',
        '/admin/notifications',
        '/admin/email-log',
        '/admin/failed-jobs',
        '/admin/cron-statistics',
        '/admin/appearance/themes',
        '/admin/appearance/customize',
        '/admin/appearance/pages',
        '/admin/appearance/navigation',
    ];
}

test('shipping admin and role permissions resolve translation keys', function () {
    $staff = $this->createStaff();
    installAndEnableModule('shipping');
    app(SyncRegisteredPermissions::class)(force: true);

    expect(__('shipping::admin.methods_title'))->toBe('Shipping methods')
        ->and(__('shipping::admin.zones_title'))->toBe('Shipping zones')
        ->and(__('shipping::admin.fulfillment_title'))->toBe('Shipments')
        ->and(__('admin.permissions.products.view'))->toBe('View products')
        ->and(__('admin.permissions.returns.view'))->toBe('View returns')
        ->and(__('admin.permissions.digital_delivery.manage'))->toBe('Manage digital deliveries')
        ->and(__('admin.permissions.plan-changes.view'))->toBe('View plan changes')
        ->and(__('admin.permissions.api.tokens'))->toBe('Manage API tokens');

    $this->actingAs($staff)
        ->get('/admin/shipping/methods')
        ->assertOk()
        ->assertSee('Shipping methods', false)
        ->assertDontSeeText('shipping::admin.methods_title');

    $this->actingAs($staff)
        ->get('/admin/roles')
        ->assertOk()
        ->assertDontSeeText('admin.permissions.products.view');

    Livewire\Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('create')
        ->assertSee('View products')
        ->assertDontSeeText('admin.permissions.products.view');
});

test('admin screens never render raw translation keys', function () {
    $staff = $this->createStaff();

    foreach (adminLocalizedRoutes() as $uri) {
        $this->actingAs($staff)
            ->get($uri)
            ->assertOk()
            ->assertDontSeeText('admin.settings.')
            ->assertDontSeeText('admin.notifications.')
            ->assertDontSeeText('admin.email_log.')
            ->assertDontSeeText('admin.failed_jobs.')
            ->assertDontSeeText('admin.dashboard.')
            ->assertDontSeeText('admin.appearance.')
            ->assertDontSeeText('admin.content.')
            ->assertDontSeeText('common.')
            ->assertDontSeeText('auth.sign_out');
    }
});

test('admin settings and dashboard follow the site locale', function () {
    app(SettingsRepository::class)->set('general', 'locale', 'nl');
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('admin.dashboard.heading', [], 'nl'), false)
        ->assertSee(__('admin.dashboard.stats.products', [], 'nl'), false)
        ->assertSee(__('admin.exit_admin', [], 'nl'), false)
        ->assertSee(__('auth.sign_out', [], 'nl'), false);

    $this->actingAs($staff)
        ->get('/admin/settings')
        ->assertOk()
        ->assertSee(__('admin.settings.title', [], 'nl'), false)
        ->assertSee(__('admin.settings.groups.general', [], 'nl'), false)
        ->assertSee(__('admin.settings.group_help.branding', [], 'nl'), false);

    $this->actingAs($staff)
        ->get('/admin/settings/general')
        ->assertOk()
        ->assertSee(__('admin.settings.fields.site_name', [], 'nl'), false)
        ->assertSee(__('admin.settings.save', [], 'nl'), false);
});

test('theme customize labels come from the theme schema keys', function () {
    app(SettingsRepository::class)->set('general', 'locale', 'nl');
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/appearance/customize')
        ->assertOk()
        ->assertSee(__('admin.appearance.theme_fields.colors.accent', [], 'nl'), false)
        ->assertSee(__('admin.appearance.theme_fields.catalog.products_per_row', [], 'nl'), false)
        ->assertSee(__('admin.appearance.customize.sections.add_hero', [], 'nl'), false);
});

test('content admin follows the site locale', function () {
    app(SettingsRepository::class)->set('general', 'locale', 'nl');
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/appearance/pages')
        ->assertOk()
        ->assertSee(__('admin.content.pages.title', [], 'nl'), false)
        ->assertSee(__('admin.content.pages.new', [], 'nl'), false);

    $this->actingAs($staff)
        ->get('/admin/appearance/navigation')
        ->assertOk()
        ->assertSee(__('admin.content.navigation.title', [], 'nl'), false)
        ->assertSee(__('admin.content.navigation.menu_names.footer_legal', [], 'nl'), false);
});
