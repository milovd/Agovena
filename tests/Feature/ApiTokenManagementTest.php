<?php

declare(strict_types=1);

use App\Agovena\Api\ApiIpAllowlist;
use App\Agovena\Api\ApiTokenAbilities;
use App\Agovena\Settings\SettingsRepository;
use App\Livewire\Admin\System\ApiTokens;
use App\Models\Customer;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin token editor renders create controls and every supported access group', function () {
    $staff = $this->createStaff([], ['api.tokens']);

    Livewire::actingAs($staff)
        ->test(ApiTokens::class)
        ->call('create')
        ->assertSee(__('admin.api_tokens.new'), false)
        ->assertSee('token-name', false)
        ->assertSee('account.read', false)
        ->assertSee('addresses.create', false)
        ->assertSee('orders.pay', false)
        ->assertSee('invoices.read', false)
        ->assertSee('credit_notes.read', false)
        ->assertSee('tickets.read', false)
        ->assertSee('subscriptions.read', false)
        ->assertSee('services.read', false)
        ->assertSee('downloads.read', false)
        ->assertSee('digital_secrets.read', false)
        ->assertSee('event_tickets.read', false)
        ->assertSee('tokens.revoke', false)
        ->assertSee('token-ip-allowlist', false);
});

test('module api routes resolve to their token access permissions', function () {
    expect(ApiTokenAbilities::forRoute('api.v1.subscriptions.index'))->toBe(ApiTokenAbilities::SUBSCRIPTIONS_READ)
        ->and(ApiTokenAbilities::forRoute('api.v1.services.show'))->toBe(ApiTokenAbilities::SERVICES_READ)
        ->and(ApiTokenAbilities::forRoute('api.v1.downloads.file'))->toBe(ApiTokenAbilities::DOWNLOADS_READ)
        ->and(ApiTokenAbilities::forRoute('api.v1.digital-secrets.index'))->toBe(ApiTokenAbilities::DIGITAL_SECRETS_READ)
        ->and(ApiTokenAbilities::forRoute('api.v1.event-tickets.show'))->toBe(ApiTokenAbilities::EVENT_TICKETS_READ)
        ->and(ApiTokenAbilities::forRoute('api.v1.unknown.index'))->toBeNull();
});

test('every authenticated api route has token ability middleware', function () {
    $routes = collect(app('router')->getRoutes())
        ->filter(static function ($route): bool {
            return str_starts_with($route->uri(), 'api/v1/')
                && in_array('auth:sanctum', $route->middleware(), true);
        });

    expect($routes)->not->toBeEmpty();
    foreach ($routes as $route) {
        expect(collect($route->gatherMiddleware())
            ->contains(fn (string $name): bool => str_starts_with($name, 'api.ability')))
            ->toBeTrue();
    }
});

test('admin token editor stores granular abilities and a normalized ip policy', function () {
    $staff = $this->createStaff([], ['api.tokens']);

    Livewire::actingAs($staff)
        ->test(ApiTokens::class)
        ->call('create')
        ->set('tokenName', 'Order automation')
        ->set('selectedAbilities', ['account.read', 'orders.read'])
        ->set('tokenIpAllowlist', "203.0.113.10\n2001:0db8:0:0:0:0:0:1")
        ->call('saveToken')
        ->assertSet('showingPasswordConfirmation', true)
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors();

    $token = $staff->fresh()->tokens()->where('name', 'Order automation')->first();

    expect($token)->not->toBeNull()
        ->and($token->abilities)->toBe(['account.read', 'orders.read'])
        ->and($token->ip_allowlist)->toBe(['203.0.113.10', '2001:db8::1']);
});

test('admin token editor updates token metadata without changing the secret', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $token = $staff->createToken('Old name', ['account.read'])->accessToken;
    $token->forceFill(['ip_allowlist' => ['203.0.113.10']])->save();
    $secretHash = $token->token;

    Livewire::actingAs($staff)
        ->test(ApiTokens::class)
        ->call('edit', $token->id)
        ->set('tokenName', 'Updated name')
        ->set('selectedAbilities', ['account.read', 'account.update'])
        ->set('tokenIpAllowlist', '')
        ->call('saveToken')
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors();

    $updated = $staff->fresh()->tokens()->findOrFail($token->id);

    expect($updated->name)->toBe('Updated name')
        ->and($updated->abilities)->toBe(['account.read', 'account.update'])
        ->and($updated->ip_allowlist)->toBe([])
        ->and($updated->token)->toBe($secretHash);
});

test('api token abilities protect account routes', function () {
    $customer = Customer::factory()->create();
    $created = $customer->user->createToken('Orders only', ['orders.read']);

    $this->withToken($created->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope');

    $this->withToken($created->plainTextToken)
        ->getJson('/api/v1/orders')
        ->assertOk();

    $this->withToken($created->plainTextToken)
        ->deleteJson('/api/v1/auth/tokens')
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope');
});

test('password token endpoint accepts a scoped ability selection', function () {
    $customer = Customer::factory()->create();

    $created = $this->postJson('/api/v1/auth/tokens', [
        'email' => $customer->email,
        'password' => 'password',
        'name' => 'Scoped integration',
        'abilities' => ['account.read'],
    ])->assertCreated()->json();

    $token = $customer->user->tokens()->where('name', 'Scoped integration')->firstOrFail();
    expect($token->abilities)->toBe(['account.read']);

    $this->withToken($created['token'])
        ->getJson('/api/v1/me')
        ->assertOk();

    $this->withToken($created['token'])
        ->getJson('/api/v1/orders')
        ->assertForbidden()
        ->assertJsonPath('code', 'insufficient_scope');
});

test('api token can revoke itself when the revoke ability is granted', function () {
    $customer = Customer::factory()->create();
    $revocable = $customer->user->createToken('Revocable', ['tokens.revoke']);

    expect($revocable->accessToken->fresh()->abilities)->toBe(['tokens.revoke']);

    $this->withToken($revocable->plainTextToken)
        ->deleteJson('/api/v1/auth/tokens')
        ->assertOk();

    expect($customer->user->tokens()->whereKey($revocable->accessToken->id)->exists())->toBeFalse();
});

test('api token ip policy is isolated per token and an empty policy allows every ip', function () {
    $customer = Customer::factory()->create();
    $allowed = $customer->user->createToken('Restricted', ['account.read']);
    $allowed->accessToken->forceFill(['ip_allowlist' => ['203.0.113.10']])->save();
    $open = $customer->user->createToken('Open', ['account.read']);
    $open->accessToken->forceFill(['ip_allowlist' => []])->save();

    $this->withToken($allowed->plainTextToken)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->getJson('/api/v1/me')
        ->assertOk();

    $this->withToken($allowed->plainTextToken)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'ip_not_allowed');

    $this->withToken($open->plainTextToken)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.50'])
        ->getJson('/api/v1/me')
        ->assertOk();
});

test('empty token ip policy cannot bypass the global api ip policy', function () {
    $customer = Customer::factory()->create();
    $created = $customer->user->createToken('Open token', ['account.read']);
    $created->accessToken->forceFill(['ip_allowlist' => []])->save();

    app(SettingsRepository::class)->set(ApiIpAllowlist::GROUP, ApiIpAllowlist::KEY, ['203.0.113.10']);

    $this->withToken($created->plainTextToken)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'ip_not_allowed');
});
