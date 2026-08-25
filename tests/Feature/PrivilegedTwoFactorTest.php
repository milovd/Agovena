<?php

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Auth\ManageUserSessions;
use App\Agovena\Auth\TotpTwoFactor;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Customer\Account\Security;
use App\Livewire\Customer\Auth\Login;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function totpCode(string $secret): string
{
    $google2fa = app(Google2FA::class);

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp());
}

test('totp accepts nearby time-step codes despite clock skew', function () {
    $totp = app(TotpTwoFactor::class);
    $google2fa = app(Google2FA::class);
    $secret = $totp->generateSecret();
    $skewed = $google2fa->oathTotp($secret, $google2fa->getTimestamp() - 4);

    expect($totp->verify($secret, $skewed))->toBeTrue();
});

test('privileged users without totp are redirected to customer security setup', function () {
    $staff = $this->createStaff(withTwoFactor: false);

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('customer.security'));

    $this->actingAs($staff)
        ->get(route('customer.security'))
        ->assertOk()
        ->assertSee(__('customer.security.required_title'), false);
});

test('customers are not forced into admin totp', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('customer.account'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('privileged login with totp requires a challenge', function () {
    $staff = $this->createStaff([
        'email' => 'owner-2fa@example.test',
        'password' => 'password',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'owner-2fa@example.test')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(session(TotpTwoFactor::SESSION_PENDING_ID))->toBe($staff->id);
});

test('valid totp code completes privileged login', function () {
    $staff = $this->createStaff([
        'email' => 'owner-totp@example.test',
        'password' => 'password',
    ]);
    $secret = (string) $staff->two_factor_secret;

    $this->withSession([
        TotpTwoFactor::SESSION_PENDING_ID => $staff->id,
        TotpTwoFactor::SESSION_PENDING_REMEMBER => false,
        TotpTwoFactor::SESSION_PENDING_INTENDED => route('admin.dashboard'),
    ]);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', totpCode($secret))
        ->call('authenticate')
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($staff);
});

test('invalid totp code is rejected', function () {
    $staff = $this->createStaff();

    $this->withSession([
        TotpTwoFactor::SESSION_PENDING_ID => $staff->id,
    ]);

    Livewire::test(TwoFactorChallenge::class)
        ->set('code', '000000')
        ->call('authenticate')
        ->assertHasErrors(['code']);

    $this->assertGuest();
});

test('recovery code completes login and is consumed', function () {
    $staff = $this->createStaff();
    $totp = app(TotpTwoFactor::class);
    $plain = ['ABCD-EFGH'];
    $staff->forceFill([
        'two_factor_recovery_codes' => $totp->hashRecoveryCodes($plain),
    ])->save();

    $this->withSession([
        TotpTwoFactor::SESSION_PENDING_ID => $staff->id,
        TotpTwoFactor::SESSION_PENDING_INTENDED => route('admin.dashboard'),
    ]);

    Livewire::test(TwoFactorChallenge::class)
        ->call('useRecovery')
        ->set('recovery_code', 'ABCD-EFGH')
        ->call('authenticate')
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($staff);
    expect($staff->fresh()?->two_factor_recovery_codes)->toBe([]);
});

test('any account can enable totp from customer security', function () {
    $user = User::factory()->create(['password' => 'password']);

    $component = Livewire::actingAs($user)->test(Security::class);
    $component->call('startSetup');

    $secret = (string) session(TotpTwoFactor::SESSION_SETUP_SECRET);
    expect($secret)->not->toBe('');

    $component
        ->set('code', totpCode($secret))
        ->call('confirmSetup')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true);

    expect($user->fresh()?->hasTwoFactorEnabled())->toBeTrue()
        ->and($component->get('recoveryCodes'))->toHaveCount(8);
});

test('privileged user can enable totp from customer security', function () {
    $staff = $this->createStaff(withTwoFactor: false);

    $component = Livewire::actingAs($staff)->test(Security::class);
    $component->call('startSetup');

    $secret = (string) session(TotpTwoFactor::SESSION_SETUP_SECRET);
    expect($secret)->not->toBe('');

    $component
        ->set('code', totpCode($secret))
        ->call('confirmSetup')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true);

    expect($staff->fresh()?->hasTwoFactorEnabled())->toBeTrue()
        ->and($component->get('recoveryCodes'))->toHaveCount(8);
});

test('disabling totp requires a recent password', function () {
    $staff = $this->createStaff([
        'password' => 'password',
    ]);

    Livewire::actingAs($staff)
        ->test(Security::class)
        ->call('disable')
        ->assertSet('showingPasswordConfirmation', true)
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors();

    expect($staff->fresh()?->hasTwoFactorEnabled())->toBeFalse();
});

test('wrong password does not confirm a sensitive action', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Security::class)
        ->call('disable')
        ->set('recentPassword', 'not-the-password')
        ->call('confirmRecentPassword')
        ->assertHasErrors(['recentPassword']);

    expect($staff->fresh()?->hasTwoFactorEnabled())->toBeTrue()
        ->and(app(ConfirmsRecentPassword::class)->confirmed())->toBeFalse();
});

test('customers who gain admin access after login must complete privileged totp', function () {
    app(SyncRegisteredPermissions::class)();
    $user = User::factory()->create(['password' => 'password']);
    $totp = app(TotpTwoFactor::class);
    $secret = $totp->generateSecret();
    $totp->enable($user, $secret, $totp->hashRecoveryCodes(['AAAA-BBBB']));

    $this->actingAs($user)
        ->withSession([TotpTwoFactor::SESSION_PRIVILEGED_AT_LOGIN => false])
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $user->assignRole('owner');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh())
        ->withSession([TotpTwoFactor::SESSION_PRIVILEGED_AT_LOGIN => false])
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    expect(session(TotpTwoFactor::SESSION_PENDING_ID))->toBe($user->id);
});

test('remembered privileged sessions must pass totp before admin', function () {
    $staff = $this->createStaff();
    $this->actingAs($staff);

    $guard = Auth::guard('web');
    $property = new ReflectionProperty($guard, 'viaRemember');
    $property->setValue($guard, true);

    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
});

test('removing admin access denies the existing session', function () {
    $staff = $this->createStaff();
    $staff->syncRoles([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($staff->fresh())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('privileged user can regenerate recovery codes after password confirmation', function () {
    $staff = $this->createStaff(['password' => 'password']);

    $component = Livewire::actingAs($staff)
        ->test(Security::class)
        ->call('regenerateRecoveryCodes')
        ->assertSet('showingPasswordConfirmation', true)
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors()
        ->assertSet('showingRecoveryCodes', true);

    expect($component->get('recoveryCodes'))->toHaveCount(8)
        ->and($staff->fresh()?->two_factor_recovery_codes)->toHaveCount(8);
});

test('customer security can revoke other database sessions', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create(['password' => 'password']);
    $this->actingAs($user);
    $currentId = session()->getId();

    DB::table(config('session.table', 'sessions'))->insert([
        'id' => 'other-session-id',
        'user_id' => $user->id,
        'ip_address' => '203.0.113.10',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/120.0',
        'payload' => base64_encode('payload'),
        'last_activity' => now()->subHour()->getTimestamp(),
    ]);

    $sessions = app(ManageUserSessions::class);
    expect($sessions->listFor($user, $currentId))->toHaveCount(1)
        ->and($sessions->listFor($user, $currentId)[0]['id'])->toBe('other-session-id');

    Livewire::actingAs($user)
        ->test(Security::class)
        ->call('revokeSession', 'other-session-id')
        ->assertHasNoErrors();

    expect(DB::table(config('session.table', 'sessions'))->where('id', 'other-session-id')->exists())->toBeFalse();
});
