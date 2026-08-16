<?php

declare(strict_types=1);

use App\Agovena\Auth\ConfirmsRecentPassword;
use App\Agovena\Auth\SetUserEmailVerification;
use App\Livewire\Admin\Customers\Show as AdminCustomerShow;
use App\Models\AuditLog;
use App\Models\Customer;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('staff can mark a linked customer email as verified', function () {
    $customer = Customer::factory()->unverified()->create();
    $staff = $this->createStaff(permissions: ['customers.view', 'customers.manage']);

    $this->withSession([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('markEmailVerified')
        ->assertHasNoErrors();

    expect($customer->user?->fresh()?->hasVerifiedEmail())->toBeTrue();

    $log = AuditLog::query()->where('action', 'user.email_verified')->sole();
    expect($log->properties)->toBe(['user_id' => $customer->user_id])
        ->and($log->subject_id)->toBe($customer->user_id);
});

test('staff can mark a linked customer email as unverified', function () {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff(permissions: ['customers.view', 'customers.manage']);

    $this->withSession([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('markEmailUnverified')
        ->assertHasNoErrors();

    expect($customer->user?->fresh()?->hasVerifiedEmail())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.email_unverified')->count())->toBe(1);
});

test('staff without customer management permission cannot change email verification', function () {
    $customer = Customer::factory()->unverified()->create();
    $staff = $this->createStaff(permissions: ['customers.view']);

    $this->withSession([ConfirmsRecentPassword::SESSION_KEY => time()]);

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('markEmailVerified')
        ->assertForbidden();

    expect($customer->user?->fresh()?->hasVerifiedEmail())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.email_verified')->exists())->toBeFalse();
});

test('setting email verification is idempotent', function () {
    $customer = Customer::factory()->unverified()->create();
    $action = app(SetUserEmailVerification::class);

    $action->handle($customer->user, false);
    $action->handle($customer->user, false);

    expect($customer->user?->fresh()?->hasVerifiedEmail())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.email_unverified')->exists())->toBeFalse();

    $action->handle($customer->user, true);
    $verifiedAt = $customer->user?->fresh()?->email_verified_at;
    $action->handle($customer->user, true);

    expect($customer->user?->fresh()?->email_verified_at?->equalTo($verifiedAt))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.email_verified')->count())->toBe(1);
});
