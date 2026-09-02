<?php

use App\Agovena\Admin\AdminRoleAssignmentPolicy;
use App\Agovena\Api\ApiIpAllowlist;
use App\Agovena\Audit\AuditLogger;
use App\Agovena\Backups\BackupManager;
use App\Agovena\Backups\BackupRunResult;
use App\Agovena\Backups\DatabaseBackupManager;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Livewire\Admin\Categories\Index;
use App\Livewire\Admin\Settings\Hub;
use App\Livewire\Admin\System\ApiTokens;
use App\Livewire\Admin\System\Backups;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin shell shows grouped catalog, sales, and system navigation', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Overview', false)
        ->assertSee(__('admin.nav_groups.catalog'), false)
        ->assertSee(__('admin.nav_groups.sales'), false)
        ->assertSee(__('admin.nav_groups.customers'), false)
        ->assertSee('Categories', false)
        ->assertSee('System', false)
        ->assertSee('Settings', false)
        ->assertSee('Currencies', false)
        ->assertSee(__('admin.exit_admin'), false)
        ->assertDontSee(__('admin.view_storefront'), false)
        ->assertDontSee('>General</a>', false)
        ->assertDontSee('Configuration', false);
});

test('settings hub lists registered groups from the admin registrar', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertSee('General', false)
        ->assertSee('Branding', false)
        ->assertSee('Store', false)
        ->assertSee('Auth', false)
        ->assertSee('ag-product-tabs', false)
        ->assertSee(__('admin.settings.fields.site_name'), false);
});

test('guest is redirected from admin', function () {
    $this->get('/admin')->assertRedirect('/login');
});

test('navigation hides settings without permission', function () {
    $staff = $this->createStaff([], ['dashboard.view']);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('>General</a>', false)
        ->assertSee('Dashboard', false);
});

test('dashboard shows real product and order counts', function () {
    $staff = $this->createStaff();
    Product::factory()->create(['status' => ProductStatus::Active]);
    Product::factory()->create(['status' => ProductStatus::Draft]);
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);
    Payment::factory()->create([
        'order_id' => $order->id,
        'status' => PaymentStatus::Paid,
        'amount' => 2500,
        'currency' => 'EUR',
    ]);

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk()
        ->assertSee('2', false)
        ->assertSee('1 active', false)
        ->assertSee($order->number, false);
});

test('settings persist via repository and admin screen', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->set('tab', 'general')
        ->set('values.site_name', 'Acme Commerce')
        ->set('values.locale', 'en')
        ->set('values.timezone', 'UTC')
        ->set('values.base_currency', 'EUR')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(SettingsRepository::class)->get('general', 'site_name'))->toBe('Acme Commerce');

    $this->actingAs($staff)
        ->get('/')
        ->assertOk()
        ->assertSee('Acme Commerce', false);
});

test('api token screen renders the per-token ip editor', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get(route('admin.api-tokens'))
        ->assertOk()
        ->assertSee(__('admin.api_tokens.add'), false)
        ->assertDontSee('api-ip-allowlist', false)
        ->assertDontSee('API access by IP address', false);

    Livewire::actingAs($staff)
        ->test(ApiTokens::class)
        ->call('create')
        ->assertSee('token-ip-allowlist', false);
});

test('api token creation completes after recent password confirmation', function () {
    $staff = $this->createStaff([], ['api.tokens']);

    $component = Livewire::actingAs($staff)
        ->test(ApiTokens::class)
        ->set('token_name', 'confirmed-create')
        ->call('createToken')
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors()
        ->assertSet('showingPasswordConfirmation', false);

    expect($staff->fresh()->tokens()->where('name', 'confirmed-create')->exists())->toBeTrue()
        ->and($component->get('plainTextToken'))->toBeString()->not->toBeEmpty();
});

test('api token completion handlers cannot bypass recent password confirmation', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $token = $staff->createToken('protected-revoke')->accessToken;

    $creation = Livewire::actingAs($staff)
        ->test(ApiTokens::class)
        ->set('token_name', 'bypass-create')
        ->call('completeTokenCreation')
        ->assertSet('showingPasswordConfirmation', true);

    Livewire::actingAs($staff)
        ->test(ApiTokens::class)
        ->call('completeTokenRevocation', $token->id)
        ->assertSet('showingPasswordConfirmation', true);

    expect($staff->fresh()->tokens()->where('name', 'bypass-create')->exists())->toBeFalse()
        ->and($staff->fresh()->tokens()->whereKey($token->id)->exists())->toBeTrue()
        ->and($creation->get('pendingPasswordAction'))->toBe('completeTokenCreation');
});

test('api token revocation completes after recent password confirmation', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $token = $staff->createToken('confirmed-revoke')->accessToken;

    Livewire::actingAs($staff)
        ->test(ApiTokens::class)
        ->call('revokeToken', $token->id)
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors()
        ->assertSet('showingPasswordConfirmation', false);

    expect($staff->fresh()->tokens()->whereKey($token->id)->exists())->toBeFalse();
});

test('api token actions reauthorize after permission removal', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $this->actingAs($staff);
    $component = new ApiTokens;

    $staff->roles()->first()->revokePermissionTo('api.tokens');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => $component->saveToken(
        app(AuditLogger::class),
        app(ApiIpAllowlist::class),
    ))->toThrow(AuthorizationException::class);
});

test('api token creation reauthorizes after permission removal during password confirmation', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $this->actingAs($staff);
    $component = new ApiTokens;
    $component->token_name = 'late-create';
    $component->createToken(app(AuditLogger::class));

    expect($component->showingPasswordConfirmation)->toBeTrue();

    $staff->roles()->firstOrFail()->revokePermissionTo('api.tokens');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component->recentPassword = 'password';
    expect(fn () => $component->confirmRecentPassword())->toThrow(AuthorizationException::class);

    expect($staff->fresh()->tokens()->where('name', 'late-create')->exists())->toBeFalse();
});

test('api token revocation reauthorizes after permission removal during password confirmation', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $token = $staff->createToken('late-revoke')->accessToken;
    $this->actingAs($staff);
    $component = new ApiTokens;
    $component->revokeToken($token->id, app(AuditLogger::class));

    expect($component->showingPasswordConfirmation)->toBeTrue();

    $staff->roles()->firstOrFail()->revokePermissionTo('api.tokens');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component->recentPassword = 'password';
    expect(fn () => $component->confirmRecentPassword())->toThrow(AuthorizationException::class);

    expect($staff->fresh()->tokens()->whereKey($token->id)->exists())->toBeTrue();
});

test('role policy performs role changes through its atomic mutation method', function () {
    $owner = $this->createStaff();
    $target = $this->createStaff([], ['users.view', 'users.update']);
    $policy = app(AdminRoleAssignmentPolicy::class);

    $updated = $policy->syncRoles($owner, $target, ['staff_limited'], 'selectedRoles');

    expect($updated->fresh()->hasRole('staff_limited'))->toBeTrue()
        ->and(fn () => $policy->syncRoles($owner, $owner, [], 'selectedRoles'))
        ->toThrow(ValidationException::class);
});

test('staff without settings update cannot save', function () {
    $staff = $this->createStaff([], ['settings.view', 'dashboard.view']);

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->set('tab', 'general')
        ->set('values.site_name', 'Nope')
        ->call('save')
        ->assertForbidden();
});

test('owner can create a category', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Index::class)
        ->call('create')
        ->set('name', 'Hosting')
        ->set('slug', 'hosting')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('categories', ['slug' => 'hosting', 'name' => 'Hosting']);
});

test('database backups screen renders operational status and creates a backup', function () {
    $staff = $this->createStaff();
    $source = tempnam(storage_path('framework'), 'admin-backup-test-');

    expect($source)->not->toBeFalse();
    file_put_contents((string) $source, 'SQLite format 3 backup fixture');

    try {
        Storage::fake('local');
        $realBackup = app(BackupManager::class)->backupSqlite((string) $source);
        expect($realBackup->success)->toBeTrue();

        app()->instance(DatabaseBackupManager::class, new class implements DatabaseBackupManager
        {
            public function backupConfiguredDatabase(): BackupRunResult
            {
                return new BackupRunResult(true, 'backups/database-test.enc');
            }
        });

        $this->actingAs($staff)
            ->get(route('admin.backups'))
            ->assertOk()
            ->assertSee(__('admin.backups.title'), false)
            ->assertSee(__('admin.backups.create'), false)
            ->assertSee(__('admin.backups.storage_label'), false);

        Livewire::actingAs($staff)
            ->test(Backups::class)
            ->call('createBackup')
            ->assertHasNoErrors()
            ->assertSet('lastResult', 'success');

        expect(Storage::disk('local')->files('backups'))->not->toBeEmpty();
    } finally {
        @unlink((string) $source);
    }
});

test('staff without backup management permission cannot create a database backup', function () {
    $staff = $this->createStaff([], ['backups.view']);

    Livewire::actingAs($staff)
        ->test(Backups::class)
        ->call('createBackup')
        ->assertForbidden();
});

test('database backups screen rechecks view permission during livewire refresh', function () {
    $staff = $this->createStaff([], ['backups.view']);

    $component = Livewire::actingAs($staff)->test(Backups::class);
    $staff->roles->firstOrFail()->revokePermissionTo('backups.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $component->call('$refresh')->assertForbidden();
});

test('database backups screen saves an interval and protects artifact actions', function () {
    $staff = $this->createStaff();

    Storage::fake('local');
    config()->set('agovena.backups.disk', 'local');
    config()->set('agovena.backups.directory', 'backups');
    Storage::disk('local')->put('backups/database-sqlite-test.enc', 'encrypted');

    Livewire::actingAs($staff)
        ->test(Backups::class)
        ->assertSee(__('admin.backups.interval_label'), false)
        ->assertSee(__('admin.backups.delete'), false)
        ->assertSee(__('admin.backups.restore'), false)
        ->set('backupInterval', 'hourly')
        ->call('saveSchedule')
        ->assertHasNoErrors()
        ->assertSee(__('admin.backups.schedule_saved'), false)
        ->call('restoreBackup', 'backups/database-sqlite-test.enc')
        ->assertSet('showingPasswordConfirmation', true)
        ->call('cancelPasswordConfirmation')
        ->call('deleteBackup', 'backups/database-sqlite-test.enc')
        ->assertSet('showingPasswordConfirmation', true)
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertSet('showingPasswordConfirmation', false);

    expect(app(SettingsRepository::class)->get('backups', 'interval'))->toBe('hourly')
        ->and(Storage::disk('local')->exists('backups/database-sqlite-test.enc'))->toBeFalse();
});
