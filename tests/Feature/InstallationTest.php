<?php

declare(strict_types=1);

use App\Agovena\Installation\InstallAgovena;
use App\Agovena\Installation\InstallationException;
use App\Agovena\Installation\InstallationRequirements;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Installation\InstallRequest;
use App\Agovena\Installation\RequirementCheck;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use App\Livewire\Installer\Wizard;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    app(InstallationState::class)->reset();
});

test('fresh application is not installed', function () {
    $state = app(InstallationState::class);

    expect($state->notInstalled())->toBeTrue()
        ->and($state->installed())->toBeFalse()
        ->and($state->installedAt())->toBeNull();
});

test('installer welcome is available when not installed', function () {
    $this->get('/install')
        ->assertOk()
        ->assertSee(__('installer.welcome.heading'), false)
        ->assertSee(__('installer.welcome.ready_title'), false)
        ->assertDontSee(__('installer.checks.ext_openssl'), false)
        ->assertSee('/vendor/agovena/logo.png', false)
        ->assertDontSee('install-welcome__logo', false);
});

test('application routes redirect to installer before installation', function () {
    $product = Product::factory()->active()->create(['slug' => 'gated-phone']);

    $this->get('/')->assertRedirect(route('install'));
    $this->get('/products/'.$product->slug)->assertRedirect(route('install'));
    $this->get('/categories')->assertRedirect(route('install'));
    $this->get('/cart')->assertRedirect(route('install'));
    $this->get('/checkout')->assertRedirect(route('install'));
    $this->get('/admin/login')->assertRedirect(route('install'));
    $this->get('/admin')->assertRedirect(route('install'));
    $this->get('/about')->assertRedirect(route('install'));

    $this->get('/install')->assertOk();
});

test('installer welcome has no redirect loop', function () {
    $response = $this->get('/install');

    $response->assertOk();
    expect($response->headers->get('Location'))->toBeNull();
});

test('admin redirects to installer when not installed', function () {
    $this->get('/admin/login')
        ->assertRedirect(route('install'));
});

test('web installation creates owner settings currency theme and lock', function () {
    Livewire::test(Wizard::class)
        ->assertSet('step', 'welcome')
        ->assertSee(__('installer.welcome.ready_title'), false)
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
        ->assertSet('step', 'catalog')
        ->assertSee(__('installer.catalog.heading'), false)
        ->call('next')
        ->assertSet('step', 'regional')
        ->set('locale', 'nl')
        ->set('timezone', 'Europe/Amsterdam')
        ->set('currency', 'EUR')
        ->call('next')
        ->assertSet('step', 'branding')
        ->call('skipBranding')
        ->assertSet('step', 'theme')
        ->assertSee(__('installer.theme.selected'), false)
        ->set('themeId', 'default')
        ->call('install')
        ->assertSet('step', 'complete')
        ->assertSet('installError', '')
        ->assertSee('Demo Shop', false)
        ->assertSee('EUR', false);

    $state = app(InstallationState::class);
    expect($state->installed())->toBeTrue()
        ->and($state->installId())->not->toBeNull()
        ->and(is_file($state->markerPath()))->toBeTrue();

    $owner = User::query()->where('email', 'owner@example.com')->first();
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

    $this->get('/')->assertOk();
    $this->get('/admin/login')->assertRedirect(route('login'));
    $this->get('/install')->assertRedirect(route('admin.dashboard'));
});

test('installer catalog presets enable modules without locking a store type', function () {
    Livewire::test(Wizard::class)
        ->call('next')
        ->set('ownerName', 'Preset Owner')
        ->set('ownerEmail', 'presets@example.com')
        ->set('ownerPassword', 'Secret-Pass-123')
        ->set('ownerPasswordConfirmation', 'Secret-Pass-123')
        ->call('next')
        ->set('siteName', 'Preset Shop')
        ->call('next')
        ->assertSet('step', 'catalog')
        ->set('presetIds', ['physical', 'digital'])
        ->call('next')
        ->set('locale', 'en')
        ->set('timezone', 'UTC')
        ->set('currency', 'EUR')
        ->call('next')
        ->call('skipBranding')
        ->call('install')
        ->assertSet('step', 'complete');

    $modules = app(ModuleManager::class);
    expect($modules->isEnabled('inventory'))->toBeTrue()
        ->and($modules->isEnabled('shipping'))->toBeTrue()
        ->and($modules->isEnabled('digital'))->toBeTrue()
        ->and(app(SettingsRepository::class)->get('store', 'presets'))->toBe(['physical', 'digital']);
});

test('cli installation can enable store presets', function () {
    $this->artisan('agovena:install', [
        '--name' => 'Cli Preset',
        '--email' => 'clipreset@example.com',
        '--password' => 'Secret-Pass-123',
        '--site-name' => 'CLI Preset Shop',
        '--locale' => 'en',
        '--timezone' => 'UTC',
        '--currency' => 'EUR',
        '--theme' => 'default',
        '--presets' => 'events',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(app(ModuleManager::class)->isEnabled('events'))->toBeTrue();
});

test('doctor warns when install lock and storage marker disagree', function () {
    $state = app(InstallationState::class);
    $state->reset();
    $state->markInstalled();

    file_put_contents($state->markerPath(), json_encode([
        'install_id' => '00000000-0000-0000-0000-000000000000',
        'installed_at' => now()->toIso8601String(),
        'app' => 'agovena',
    ], JSON_THROW_ON_ERROR));

    $this->artisan('agovena:doctor')
        ->expectsOutputToContain('database install id does not match the storage marker')
        ->assertSuccessful();
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

    $this->get('/install')->assertRedirect(route('admin.dashboard'));

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
    expect(User::query()->where('email', 'cli@example.com')->exists())->toBeTrue();
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

    expect(User::query()->where('email', 'other@example.com')->exists())->toBeFalse();
});

test('interrupted installation remains recoverable and is not marked installed', function () {
    app(SyncRegisteredPermissions::class)(force: true);
    Role::findOrCreate('owner', 'web');

    $partial = User::factory()->create([
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
        ->and(User::query()->count())->toBe(1);
});

test('installer refuses creating a second owner email after partial owner exists', function () {
    app(SyncRegisteredPermissions::class)(force: true);
    Role::findOrCreate('owner', 'web');

    $partial = User::factory()->create(['email' => 'first-owner@example.com']);
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

test('healthy requirements stay concise in the installer while warnings do not block continue', function () {
    $requirements = app(InstallationRequirements::class);

    expect($requirements->ready())->toBeTrue();

    $component = Livewire::test(Wizard::class);
    $component->assertSee(__('installer.welcome.ready_title'), false)
        ->assertDontSee(__('installer.checks.ext_mbstring'), false);

    $warnings = array_values(array_filter(
        $requirements->checks(),
        static fn (RequirementCheck $check): bool => ! $check->required && ! $check->passed,
    ));

    if ($warnings !== []) {
        $component->assertSee(__('installer.welcome.warnings_summary', ['count' => count($warnings)]), false);
    }

    $component->call('next')->assertSet('step', 'owner');
});

test('blocking requirement failure is surfaced and blocks continue', function () {
    $requirements = Mockery::mock(InstallationRequirements::class);
    $requirements->shouldReceive('checks')->andReturn([
        new RequirementCheck('database', 'installer.checks.database', false, true, 'connection refused'),
        new RequirementCheck('storage_link', 'installer.checks.storage_link', false, false, 'optional'),
    ]);
    $requirements->shouldReceive('ready')->andReturn(false);
    $requirements->shouldReceive('failures')->andReturn([
        new RequirementCheck('database', 'installer.checks.database', false, true, 'connection refused'),
    ]);
    app()->instance(InstallationRequirements::class, $requirements);

    Livewire::test(Wizard::class)
        ->assertSee(__('installer.welcome.blocked_title'), false)
        ->assertSee(__('installer.checks.database'), false)
        ->assertSee('connection refused', false)
        ->assertDontSee(__('installer.welcome.ready_title'), false)
        ->call('next')
        ->assertHasErrors('welcome')
        ->assertSet('step', 'welcome');
});

test('official Agovena logo is independent from merchant branding', function () {
    $this->get('/install')
        ->assertOk()
        ->assertSee('/vendor/agovena/logo.png', false)
        ->assertDontSee('storage/branding', false);
});

test('installer branding rejects svg uploads', function () {
    Livewire::test(Wizard::class)
        ->set('step', 'branding')
        ->set('logo', UploadedFile::fake()->create('logo.svg', 20, 'image/svg+xml'))
        ->call('next')
        ->assertHasErrors(['logo']);
});
