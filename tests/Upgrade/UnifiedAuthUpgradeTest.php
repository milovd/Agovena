<?php

declare(strict_types=1);

use App\Agovena\Auth\UnifyAuthenticatedUsers;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LegacyPreUnifySchema;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Legacy staff_users rebuild uses SQLite pragmas.');
    }
});

test('legacy staff and customer identities upgrade onto users', function () {
    $seed = LegacyPreUnifySchema::installAndSeed();

    expect(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('staff_users'))->toBeTrue();

    (new UnifyAuthenticatedUsers)();

    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('staff_users'))->toBeFalse()
        ->and(Schema::hasTable('customer_password_reset_tokens'))->toBeFalse()
        ->and(Schema::hasColumn('customers', 'password'))->toBeFalse()
        ->and(Schema::hasColumn('customers', 'user_id'))->toBeTrue();

    $owner = User::query()->where('email', $seed['owner_email'])->first();
    $customer = User::query()->where('email', $seed['customer_email'])->first();
    $merged = User::query()->where('email', $seed['merge_email'])->first();

    expect($owner)->not->toBeNull()
        ->and($customer)->not->toBeNull()
        ->and($merged)->not->toBeNull()
        ->and(User::query()->where('email', $seed['merge_email'])->count())->toBe(1);

    expect(Auth::attempt(['email' => $seed['owner_email'], 'password' => 'password']))->toBeTrue();
    Auth::logout();
    expect(Auth::attempt(['email' => $seed['customer_email'], 'password' => 'password']))->toBeTrue();
    Auth::logout();

    expect(Auth::attempt(['email' => $seed['merge_email'], 'password' => 'staff-password']))->toBeTrue();
    Auth::logout();
    expect(Auth::attempt(['email' => $seed['merge_email'], 'password' => 'customer-password']))->toBeFalse();

    expect($owner->canAccessAdmin())->toBeTrue()
        ->and($owner->hasRole('owner'))->toBeTrue()
        ->and($merged->hasRole('editor'))->toBeTrue()
        ->and($customer->canAccessAdmin())->toBeFalse()
        ->and($customer->customer?->id)->toBe($seed['customer_id']);

    expect(DB::table('customers')->where('id', $seed['merge_customer_id'])->value('user_id'))->toBe($merged->id)
        ->and(DB::table('orders')->where('id', $seed['order_id'])->value('customer_id'))->toBe($seed['customer_id'])
        ->and(DB::table('tickets')->where('id', $seed['ticket_id'])->value('staff_user_id'))->toBe($owner->id)
        ->and(DB::table('customer_credit_entries')->where('id', $seed['credit_id'])->value('staff_user_id'))->toBe($owner->id)
        ->and(DB::table('model_has_roles')->where('model_type', 'App\\Models\\StaffUser')->count())->toBe(0)
        ->and(DB::table('roles')->where('guard_name', 'staff')->count())->toBe(0)
        ->and(DB::table('users')->whereNotNull('remember_token')->count())->toBe(0)
        ->and(DB::table('sessions')->count())->toBe(0);
});

test('unify migration is idempotent after a successful upgrade', function () {
    LegacyPreUnifySchema::installAndSeed();
    (new UnifyAuthenticatedUsers)();
    (new UnifyAuthenticatedUsers)();

    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('staff_users'))->toBeFalse()
        ->and(User::query()->count())->toBe(3);
});

test('legacy remember tokens cannot authenticate after upgrade', function () {
    $seed = LegacyPreUnifySchema::installAndSeed();
    $legacyToken = DB::table('staff_users')->where('email', $seed['owner_email'])->value('remember_token');
    expect($legacyToken)->not->toBeNull();

    (new UnifyAuthenticatedUsers)();

    $user = User::query()->where('email', $seed['owner_email'])->first();
    expect($user?->remember_token)->toBeNull()
        ->and(Auth::viaRemember())->toBeFalse();

    $this->assertGuest();
});
