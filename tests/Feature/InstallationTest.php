<?php

declare(strict_types=1);

use App\Agovena\Installation\InstallAgovena;
use App\Agovena\Installation\InstallationException;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Installation\InstallRequest;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use App\Livewire\Installer\Wizard;
use App\Models\StaffUser;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('fresh application is not installed', function () {
    $state = app(InstallationState::class);

    expect($state->notInstalled())->toBeTrue()
        ->and($state->installed())->toBeFalse()
        ->and($state->installedAt())->toBeNull();
});

test('installer welcome is available when not installed', function () {
    $this->get('/install')
        ->assertOk()
        ->assertSee(__('installer.welcome.heading'), false);
});

test('admin redirects to installer when not installed', function () {
    $this->get('/admin/login')
        ->assertRedirect(route('install'));
});

test('web installation creates owner settings currency theme and lock', function () {
    Livewire::test(Wizard::class)
        ->assertSet('step', 'welcome')
        ->call('next')
        ->assertSet('step', 'owner')
        ->set('ownerName', 'Store Owner')
        ->set('ownerEmail', 'owner@example.com')
        ->set('ownerPassword', 'Secret-Pass-123')
        ->set('ownerPasswordConfirmation', 'Secret-Pass-123')
        ->call('next')
        ->assertSet('step', 'store')
        ->set('siteName', 'Demo Shop')
        ->call('next')
        ->assertSet('step', 'regional')
        ->set('locale', 'nl')
        ->set('timezone', 'Europe/Amsterdam')
        ->set('currency', 'EUR')
        ->call('next')
        ->assertSet('step', 'branding')
        ->call('skipBranding')
        ->assertSet('step', 'theme')
        ->set('themeId', 'default')
        ->call('install')
        ->assertSet('step', 'complete')
        ->assertSet('installError', '');

    $state = app(InstallationState::class);
    expect($state->installed())->toBeTrue()
        ->and($state->installId())->not->toBeNull()
        ->and(is_file($state->markerPath()))->toBeTrue();

    $owner = StaffUser::query()->where('email', 'owner@example.com')->first();
    expect($owner)->not->toBeNull()
        ->and($owner->hasRole('owner'))->toBeTrue()
        ->and($owner->can('users.view'))->toBeTrue()
        ->and($owner->can('roles.view'))->toBeTrue()
        ->and(Hash::check('Secret-Pass-123', $owner->password))->toBeTrue();

    $settings = app(SettingsRepository::class);
    expect($settings->get('general', 'site_name'))->toBe('Demo Shop')
        ->and($settings->get('general', 'locale'))->toBe('nl')
        ->and($settings->get('general', 'timezone'))->toBe('Europe/Amsterdam')
        ->and($settings->get('general', 'base_currency'))->toBe('EUR')
        ->and($settings->get('appearance', 'active_theme'))->toBe('default');

    expect(app(ThemeManager::class)->active()->id)->toBe('default');
});

test('installer is inaccessible after installation', function () {
    app(InstallAgovena::class)(new InstallRequest(
        ownerName: 'Owner',
        ownerEmail: 'locked@example.com',
        ownerPassword: 'Secret-Pass-123',
        siteName: 'Locked Shop',
        locale: 'en',
        timezone: 'UTC',
        currencyCode: 'USD',
        themeId: 'default',
    ));

    $this->get('/install')->assertRedirect(route('admin.login'));

    Livewire::test(Wizard::class)->assertStatus(404);
});

test('cli installation succeeds', function () {
    $this->artisan('agovena:install', [
        '--name' => 'Cli Owner',
        '--email' => 'cli@example.com',
        '--password' => 'Secret-Pass-123',
        '--site-name' => 'CLI Shop',
        '--locale' => 'en',
        '--timezone' => 'UTC',
        '--currency' => 'GBP',
        '--theme' => 'default',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(app(InstallationState::class)->installed())->toBeTrue();
    expect(StaffUser::query()->where('email', 'cli@example.com')->exists())->toBeTrue();
    expect(app(SettingsRepository::class)->get('general', 'base_currency'))->toBe('GBP');
});

test('non-interactive cli install fails without required options', function () {
    $this->artisan('agovena:install', ['--no-interaction' => true])
        ->assertFailed();

    expect(app(InstallationState::class)->notInstalled())->toBeTrue();
});

test('repeated installation is refused', function () {
    $install = app(InstallAgovena::class);
    $request = new InstallRequest(
        ownerName: 'Owner',
        ownerEmail: 'once@example.com',
        ownerPassword: 'Secret-Pass-123',
        siteName: 'Once Shop',
        locale: 'en',
        timezone: 'UTC',
        currencyCode: 'EUR',
        themeId: 'default',
    );

    $install($request);

    expect(fn () => $install($request))->toThrow(InstallationException::class);

    $this->artisan('agovena:install', [
        '--name' => 'Other',
        '--email' => 'other@example.com',
        '--password' => 'Secret-Pass-123',
        '--site-name' => 'Other',
        '--locale' => 'en',
        '--timezone' => 'UTC',
        '--currency' => 'EUR',
        '--theme' => 'default',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(StaffUser::query()->where('email', 'other@example.com')->exists())->toBeFalse();
});

test('interrupted installation remains recoverable and is not marked installed', function () {
    app(SyncRegisteredPermissions::class)(force: true);
    Role::findOrCreate('owner', 'staff');

    $partial = StaffUser::factory()->create([
        'email' => 'partial@example.com',
        'name' => 'Partial',
    ]);
    $partial->assignRole('owner');

    expect(app(InstallationState::class)->notInstalled())->toBeTrue();

    app(InstallAgovena::class)(new InstallRequest(
        ownerName: 'Recovered Owner',
        ownerEmail: 'partial@example.com',
        ownerPassword: 'Secret-Pass-123',
        siteName: 'Recovered Shop',
        locale: 'en',
        timezone: 'UTC',
        currencyCode: 'EUR',
        themeId: 'default',
    ));

    expect(app(InstallationState::class)->installed())->toBeTrue()
        ->and(app(SettingsRepository::class)->get('general', 'site_name'))->toBe('Recovered Shop')
        ->and(StaffUser::query()->count())->toBe(1);
});

test('installer refuses creating a second owner email after partial owner exists', function () {
    app(SyncRegisteredPermissions::class)(force: true);
    Role::findOrCreate('owner', 'staff');

    $partial = StaffUser::factory()->create(['email' => 'first-owner@example.com']);
    $partial->assignRole('owner');

    expect(fn () => app(InstallAgovena::class)(new InstallRequest(
        ownerName: 'Second',
        ownerEmail: 'second-owner@example.com',
        ownerPassword: 'Secret-Pass-123',
        siteName: 'Shop',
        locale: 'en',
        timezone: 'UTC',
        currencyCode: 'EUR',
        themeId: 'default',
    )))->toThrow(InstallationException::class);

    expect(app(InstallationState::class)->notInstalled())->toBeTrue();
});

test('doctor reports installation readiness', function () {
    $this->artisan('agovena:doctor')->assertSuccessful();
});
