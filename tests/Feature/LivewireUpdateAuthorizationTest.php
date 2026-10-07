<?php

declare(strict_types=1);

use App\Agovena\Abuse\SecurityAbuseService;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/*
 * Livewire update requests (/livewire/update) only re-run route middleware
 * that is registered as persistent. These tests load a page normally, change
 * the user's access, then replay the component snapshot the browser still
 * holds, exactly as an open tab would.
 */

function livewireSnapshot(TestResponse $page, string $component): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', (string) $page->getContent(), $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5);
        if ((json_decode($snapshot, true)['memo']['name'] ?? null) === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException("Component [{$component}] was not rendered.");
}

function livewireUpdate(string $snapshot, array $calls = [], array $updates = []): TestResponse
{
    return test()->withHeaders(['X-Livewire' => '1'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => $calls,
        ]],
    ]);
}

function refreshCall(): array
{
    return [['path' => '', 'method' => '$refresh', 'params' => []]];
}

test('an open admin component keeps working for staff who still have access', function () {
    $staff = $this->createStaff();
    $snapshot = livewireSnapshot($this->actingAs($staff)->get('/admin/subscriptions')->assertOk(), 'app.agovena.recurring.http.livewire.admin.subscriptions-index');

    livewireUpdate($snapshot, refreshCall())->assertOk();
});

test('suspending staff stops their open admin components', function () {
    $staff = $this->createStaff();
    $snapshot = livewireSnapshot($this->actingAs($staff)->get('/admin/subscriptions')->assertOk(), 'app.agovena.recurring.http.livewire.admin.subscriptions-index');

    app(SecurityAbuseService::class)->suspendUser($staff, 'test');

    livewireUpdate($snapshot, refreshCall())->assertForbidden();
});

test('removing all staff permissions stops their open admin components', function () {
    $staff = $this->createStaff();
    $snapshot = livewireSnapshot($this->actingAs($staff)->get('/admin/subscriptions')->assertOk(), 'app.agovena.recurring.http.livewire.admin.subscriptions-index');

    $staff->syncRoles([]);
    $staff->syncPermissions([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    livewireUpdate($snapshot, refreshCall())->assertForbidden();
});

test('revoking the view permission stops an open admin screen from re-rendering', function () {
    $staff = $this->createStaff(permissions: ['subscriptions.view', 'orders.view']);
    $snapshot = livewireSnapshot($this->actingAs($staff)->get('/admin/subscriptions')->assertOk(), 'app.agovena.recurring.http.livewire.admin.subscriptions-index');

    Role::findByName('staff_limited', User::GUARD)->revokePermissionTo('subscriptions.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    livewireUpdate($snapshot, refreshCall())->assertForbidden();
});

test('suspending a customer stops their open account components', function () {
    $customer = Customer::factory()->create();
    $user = $customer->user;
    $user->forceFill(['email_verified_at' => now()])->save();
    $snapshot = livewireSnapshot($this->actingAs($user)->get(route('customer.profile'))->assertOk(), 'customer.account.profile');

    app(SecurityAbuseService::class)->suspendUser($user, 'test');

    livewireUpdate($snapshot, refreshCall())->assertForbidden();
});

test('module admin pages enforce the same suspension and privileged 2FA rules as core admin pages', function () {
    $staff = $this->createStaff();
    app(SecurityAbuseService::class)->suspendUser($staff, 'test');
    $this->actingAs($staff)->get('/admin/orders')->assertForbidden();
    $this->actingAs($staff)->get('/admin/subscriptions')->assertForbidden();

    $withoutTwoFactor = $this->createStaff(['email' => 'no-2fa@example.test'], withTwoFactor: false);
    $core = $this->actingAs($withoutTwoFactor)->get('/admin/orders');
    $module = $this->actingAs($withoutTwoFactor)->get('/admin/subscriptions');
    expect($module->status())->toBe($core->status())
        ->and($module->headers->get('Location'))->toBe($core->headers->get('Location'));
});

test('module customer pages enforce account suspension like core account pages', function () {
    $customer = Customer::factory()->create();
    $customer->user->forceFill(['email_verified_at' => now()])->save();
    app(SecurityAbuseService::class)->suspendUser($customer->user, 'test');

    $this->actingAs($customer->user)->get(route('customer.profile'))->assertForbidden();
    $this->actingAs($customer->user)->get(route('customer.subscriptions'))->assertForbidden();
});
