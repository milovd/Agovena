<?php

declare(strict_types=1);

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Maintenance\MaintenanceMode;
use App\Livewire\Admin\System\Maintenance as AdminMaintenance;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function confirmMaintenancePassword(User $staff): void
{
    session([
        ConfirmsRecentPassword::SESSION_KEY => time(),
        ConfirmsRecentPassword::SESSION_USER_KEY => $staff->id,
    ]);
}

test('maintenance.manage is a registered permission synced to the owner role with labels in both locales', function () {
    $this->createStaff();

    expect(app(AdminRegistrar::class)->permissions())->toHaveKey('maintenance.manage')
        ->and(Role::findByName('owner', User::GUARD)->hasPermissionTo('maintenance.manage'))->toBeTrue();

    foreach (['en', 'nl'] as $locale) {
        expect(trans('admin.permissions.maintenance.manage', [], $locale))->not->toBe('admin.permissions.maintenance.manage')
            ->and(trans('admin.maintenance.title', [], $locale))->not->toBe('admin.maintenance.title')
            ->and(trans('errors.maintenance.heading', [], $locale))->not->toBe('errors.maintenance.heading')
            ->and(trans('storefront.maintenance_notice.text', [], $locale))->not->toBe('storefront.maintenance_notice.text')
            ->and(trans('api.maintenance', [], $locale))->not->toBe('api.maintenance');
    }
});

test('the maintenance screen is listed in the system navigation behind its permission', function () {
    $item = collect(app(AdminRegistrar::class)->navigationItems())->firstWhere('id', 'maintenance');

    expect($item)->not->toBeNull()
        ->and($item->href)->toBe('/admin/maintenance')
        ->and($item->group)->toBe('admin.nav_groups.system')
        ->and($item->permission)->toBe('maintenance.manage');
});

test('an owner turns maintenance on and off from the admin after confirming the password', function () {
    $this->freezeTime();
    $owner = $this->createStaff();
    confirmMaintenancePassword($owner);
    $endsAt = now()->addHours(3)->timezone(config('app.timezone'))->format('Y-m-d\TH:i');

    Livewire::actingAs($owner)
        ->test(AdminMaintenance::class)
        ->assertSee(__('admin.maintenance.status_off'))
        ->set('message', '  Back after the stock count.  ')
        ->set('endsAt', $endsAt)
        ->call('enable')
        ->assertHasNoErrors()
        ->assertSet('showingPasswordConfirmation', false)
        ->assertSee(__('admin.maintenance.status_on'));

    $state = app(MaintenanceMode::class)->state();
    expect($state->enabled)->toBeTrue()
        ->and($state->message)->toBe('Back after the stock count.')
        ->and($state->endsAt?->timezone(config('app.timezone'))->format('Y-m-d\TH:i'))->toBe($endsAt);

    Livewire::actingAs($owner)
        ->test(AdminMaintenance::class)
        ->call('disable')
        ->assertHasNoErrors();

    expect(app(MaintenanceMode::class)->state()->enabled)->toBeFalse();

    $logs = AuditLog::query()->where('action', 'like', 'maintenance.%')->orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['maintenance.enabled', 'maintenance.disabled'])
        ->and($logs->pluck('actor_type')->unique()->all())->toBe(['staff'])
        ->and($logs->first()->actor_id)->toBe($owner->id)
        ->and($logs->first()->after['message'] ?? null)->toBe('Back after the stock count.');
});

test('updating the message while maintenance is on writes an update audit entry', function () {
    $owner = $this->createStaff();
    confirmMaintenancePassword($owner);
    app(MaintenanceMode::class)->enable('First message', null);

    Livewire::actingAs($owner)
        ->test(AdminMaintenance::class)
        ->assertSet('message', 'First message')
        ->set('message', 'Second message')
        ->call('enable')
        ->assertHasNoErrors();

    expect(app(MaintenanceMode::class)->state()->message)->toBe('Second message')
        ->and(AuditLog::query()->where('action', 'maintenance.updated')->count())->toBe(1);
});

test('turning maintenance on or off requires a recent password', function () {
    $owner = $this->createStaff();

    Livewire::actingAs($owner)
        ->test(AdminMaintenance::class)
        ->set('message', 'Pending')
        ->call('enable')
        ->assertSet('showingPasswordConfirmation', true);

    expect(app(MaintenanceMode::class)->state()->enabled)->toBeFalse();

    app(MaintenanceMode::class)->enable(null, null);

    Livewire::actingAs($owner)
        ->test(AdminMaintenance::class)
        ->call('disable')
        ->assertSet('showingPasswordConfirmation', true);

    expect(app(MaintenanceMode::class)->state()->enabled)->toBeTrue();
});

test('the maintenance form validates the message length and the expected end time', function () {
    $owner = $this->createStaff();
    confirmMaintenancePassword($owner);

    Livewire::actingAs($owner)
        ->test(AdminMaintenance::class)
        ->set('message', str_repeat('a', MaintenanceMode::MESSAGE_MAX_LENGTH + 1))
        ->set('endsAt', now()->subHour()->format('Y-m-d\TH:i'))
        ->call('enable')
        ->assertHasErrors(['message', 'endsAt']);

    expect(app(MaintenanceMode::class)->state()->enabled)->toBeFalse();
});

test('staff without maintenance.manage cannot open or use the maintenance screen', function () {
    $staff = $this->createStaff(permissions: ['settings.view', 'settings.update']);
    confirmMaintenancePassword($staff);

    $this->actingAs($staff)->get('/admin/maintenance')->assertForbidden();

    Livewire::actingAs($staff)
        ->test(AdminMaintenance::class)
        ->assertForbidden();

    expect(app(MaintenanceMode::class)->state()->enabled)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'like', 'maintenance.%')->count())->toBe(0);
});

test('guests and customers are not let into the maintenance screen', function () {
    $this->get('/admin/maintenance')->assertRedirect();

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($customer)->get('/admin/maintenance')->assertForbidden();
});

test('an owner can open the maintenance screen through the admin route', function () {
    $owner = $this->createStaff();

    $this->actingAs($owner)->get('/admin/maintenance')
        ->assertOk()
        ->assertSee(__('admin.maintenance.title'))
        ->assertSee('agovena:maintenance');
});
