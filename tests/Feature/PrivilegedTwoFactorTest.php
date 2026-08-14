<?php

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Auth\TotpTwoFactor;
use App\Livewire\Admin\Security\TwoFactor;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Customer\Auth\Login;
use App\Models\User;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function totpCode(string $secret): string
{
    $google2fa = app(Google2FA::class);

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp());
}

test('privileged users without totp are redirected to security setup', function () {
    $staff = $this->createStaff(withTwoFactor: false);

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.security.two-factor'));

    $this->actingAs($staff)
        ->get(route('admin.security.two-factor'))
        ->assertOk()
        ->assertSee(__('admin.security.required_title'), false);
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

test('privileged user can enable totp from admin security', function () {
    $staff = $this->createStaff(withTwoFactor: false);

    $component = Livewire::actingAs($staff)->test(TwoFactor::class);
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
        ->test(TwoFactor::class)
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
        ->test(TwoFactor::class)
        ->call('disable')
        ->set('recentPassword', 'not-the-password')
        ->call('confirmRecentPassword')
        ->assertHasErrors(['recentPassword']);

    expect($staff->fresh()?->hasTwoFactorEnabled())->toBeTrue()
        ->and(app(ConfirmsRecentPassword::class)->confirmed())->toBeFalse();
});
