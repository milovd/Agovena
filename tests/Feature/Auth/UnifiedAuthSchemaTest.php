<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('fresh schema includes users and no staff_users table', function () {
    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('staff_users'))->toBeFalse()
        ->and(Schema::hasColumn('customers', 'user_id'))->toBeTrue()
        ->and(Schema::hasColumn('customers', 'password'))->toBeFalse();
});

test('admin user remains a storefront customer account', function () {
    $staff = $this->createStaff(['email' => 'owner@example.com']);

    expect($staff->customer)->not->toBeNull()
        ->and($staff->customer?->email)->toBe('owner@example.com')
        ->and($staff->canAccessAdmin())->toBeTrue();

    $this->actingAs($staff)
        ->get(route('customer.account'))
        ->assertOk();

    $this->actingAs($staff)
        ->get('/admin')
        ->assertOk();
});

test('normal user can use account but not admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('customer.account'))
        ->assertOk();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('auth configuration has a single web guard', function () {
    expect(config('auth.guards'))->toHaveKey('web')
        ->and(config('auth.guards'))->not->toHaveKey('staff')
        ->and(config('auth.guards'))->not->toHaveKey('customer')
        ->and(config('auth.providers'))->toHaveKey('users')
        ->and(config('auth.providers'))->not->toHaveKey('staff')
        ->and(config('auth.defaults.guard'))->toBe('web');
});
