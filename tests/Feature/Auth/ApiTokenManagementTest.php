<?php

declare(strict_types=1);

use App\Agovena\Api\ApiTokenAbilities;
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

test('admin token list does not label a scoped token as full access', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $scoped = $staff->createToken('Scoped integration', ['account.read'])->accessToken;
    $unrestricted = $staff->createToken('Unrestricted integration', ['*'])->accessToken;

    $html = Livewire::actingAs($staff)->test(ApiTokens::class)->html();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html, LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    $xpath = new DOMXPath($document);
    $scopedRow = $xpath->query('//tr[@*[name()="wire:key"]="pat-'.$scoped->id.'"]')->item(0);
    $unrestrictedRow = $xpath->query('//tr[@*[name()="wire:key"]="pat-'.$unrestricted->id.'"]')->item(0);
    expect($scopedRow)->not->toBeNull()
        ->and($unrestrictedRow)->not->toBeNull()
        ->and($xpath->query('./td[2]//span[contains(@class, "ag-badge")]', $scopedRow)->length)->toBe(0)
        ->and($xpath->query('./td[2]//span[contains(@class, "ag-badge")]', $unrestrictedRow)->length)->toBe(1)
        ->and($xpath->query('./td[2]', $unrestrictedRow)->item(0)->textContent)->toContain(__('admin.api_tokens.full_access'));
});

test('admin token list keeps token facts and named actions in accessible mobile rows', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $token = $staff->createToken('Scoped integration', ['account.read'])->accessToken;

    $html = Livewire::actingAs($staff)->test(ApiTokens::class)->html();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html, LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    $xpath = new DOMXPath($document);
    $table = $xpath->query('//table[contains(@class, "ag-table--mobile-cards") and contains(@class, "ag-table--api-tokens")]')->item(0);
    $row = $xpath->query('//tr[@*[name()="wire:key"]="pat-'.$token->id.'"]')->item(0);
    expect($table)->not->toBeNull()
        ->and($row)->not->toBeNull()
        ->and($xpath->query('./td', $row)->length)->toBe(5)
        ->and(array_map(static fn (DOMNode $cell): string => $cell->attributes->getNamedItem('data-label')?->nodeValue ?? '', iterator_to_array($xpath->query('./td', $row))))
        ->toBe([
            __('admin.api_tokens.name'),
            __('admin.api_tokens.access'),
            __('admin.api_tokens.ip_label'),
            __('admin.api_tokens.last_used'),
            __('common.actions'),
        ])
        ->and($xpath->query('./td[last()]//button[@aria-label]', $row)->length)->toBe(2);
});

test('admin token mobile labels and actions are localized in English and Dutch', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $staff->createToken('Scoped integration', ['account.read']);

    $labelsByLocale = [];
    foreach (['en', 'nl'] as $locale) {
        app()->setLocale($locale);
        $lastUsed = __('admin.api_tokens.last_used');
        $actions = __('common.actions');
        expect($lastUsed)->not->toBe('admin.api_tokens.last_used')
            ->and($actions)->not->toBe('common.actions');
        $labelsByLocale[$locale] = [$lastUsed, $actions];
        $html = Livewire::actingAs($staff)->test(ApiTokens::class)->html();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $row = $xpath->query('//section[contains(@class, "ag-api-token-list")]//tbody/tr')->item(0);
        expect($row)->not->toBeNull()
            ->and($xpath->query('./td[@data-label="'.__('admin.api_tokens.last_used').'" and @data-label!=""]', $row)->length)->toBe(1)
            ->and($xpath->query('./td[@data-label="'.__('common.actions').'" and @data-label!=""]//button[@aria-label]', $row)->length)->toBe(2);
    }
    expect($labelsByLocale['nl'][0])->not->toBe($labelsByLocale['en'][0])
        ->and($labelsByLocale['nl'][1])->not->toBe($labelsByLocale['en'][1]);
});

test('admin token list renders a standalone empty state without an empty table row', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $html = Livewire::actingAs($staff)->test(ApiTokens::class)->html();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html, LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    expect($xpath->query('//section[contains(@class, "ag-api-token-list")]//div[contains(@class, "ag-empty") and @role="status"]')->length)->toBe(1)
        ->and($xpath->query('//section[contains(@class, "ag-api-token-list")]//table')->length)->toBe(0);
});

test('full access is shown once in the access column rather than repeated below the token name', function () {
    $staff = $this->createStaff([], ['api.tokens']);
    $token = $staff->createToken('Full integration', ['*'])->accessToken;
    $html = Livewire::actingAs($staff)->test(ApiTokens::class)->html();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html, LIBXML_NONET);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($document);
    $row = $xpath->query('//tr[@*[name()="wire:key"]="pat-'.$token->id.'"]')->item(0);
    expect($row)->not->toBeNull()
        ->and($xpath->query('./td[1]//span[contains(@class, "ag-muted")]', $row)->length)->toBe(0)
        ->and($xpath->query('./td[2]//span[contains(@class, "ag-badge")]', $row)->length)->toBe(1);
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
