<?php

declare(strict_types=1);

use Agovena\Extensions\CloudflareDomain\CloudflareApi;
use Agovena\Extensions\CloudflareDomain\CloudflareDnsApi;
use Agovena\Extensions\CloudflareDomain\CloudflareDnsProvider;
use Agovena\Extensions\CloudflareDomain\CloudflareRegistrar;
use Agovena\Extensions\CloudflareDomain\CloudflareRegistrarOperationNotSupported;
use Agovena\Extensions\CloudflareDomain\HttpCloudflareApi;
use Agovena\Extensions\CloudflareDomain\HttpCloudflareDnsApi;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainRegistrarRegistry;
use Agovena\Modules\Domains\DomainService;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    installAndEnableModules(['domains']);
    app(ExtensionManager::class)->discover();
});

/**
 * Cloudflare Registrar workflow_status fixture, shaped like the documented
 * POST /registrations and GET /registration-status responses.
 *
 * @param  array<string, mixed>  $context
 * @return array<string, mixed>
 */
function cloudflareWorkflow(string $state, string $domain = 'example.test', array $context = [], array $extra = []): array
{
    return [
        'success' => true,
        'errors' => [],
        'messages' => [],
        'result' => array_merge([
            'state' => $state,
            'completed' => in_array($state, ['succeeded', 'failed'], true),
            'created_at' => '2026-10-05T10:00:00Z',
            'updated_at' => '2026-10-05T10:00:03Z',
            'context' => array_merge(['domain_name' => $domain], $context),
            'links' => [
                'self' => '/accounts/fixture/registrar/registrations/'.$domain.'/registration-status',
                'resource' => '/accounts/fixture/registrar/registrations/'.$domain,
            ],
        ], $extra),
    ];
}

/** @return array<string, mixed> */
function cloudflareCheck(string $domain = 'example.test', bool $registrable = true, string $tier = 'standard', ?string $reason = null): array
{
    $entry = ['name' => $domain, 'registrable' => $registrable, 'tier' => $tier];
    if ($registrable) {
        $entry['pricing'] = ['currency' => 'USD', 'registration_cost' => '10.11', 'renewal_cost' => '10.11'];
    }
    if ($reason !== null) {
        $entry['reason'] = $reason;
    }

    return ['success' => true, 'result' => ['domains' => [$entry]]];
}

function cloudflareRegistration(string $domain = 'example.test', bool $autoRenew = false): DomainRegistration
{
    return new DomainRegistration(['domain_name' => $domain, 'auto_renew' => $autoRenew]);
}

function configureCloudflareSettings(): ExtensionSettingsRepository
{
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('cloudflare-domain', 'account_id', 'fixtureaccount');
    $settings->set('cloudflare-domain', 'api_token', 'fixture-secret-token', secret: true);

    return $settings;
}

it('keeps a Cloudflare registration workflow in progress instead of treating it as active', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->once()->with('example.test')->andReturnNull();
    $api->shouldReceive('check')->once()->with(['example.test'])->andReturn(cloudflareCheck());
    $api->shouldReceive('register')->once()->with('example.test', ['auto_renew' => false])->andReturn(cloudflareWorkflow('in_progress'));

    $result = (new CloudflareRegistrar($api))->register(cloudflareRegistration());

    expect($result['status'])->toBe('registering')
        ->and($result['provider_reference'])->toBe('example.test')
        ->and($result['meta']['workflow_state'])->toBe('in_progress');
});

it('maps a succeeded Cloudflare registration workflow to active with the registry expiry', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->once()->andReturnNull();
    $api->shouldReceive('check')->once()->andReturn(cloudflareCheck());
    $api->shouldReceive('register')->once()->with('example.test', ['auto_renew' => true])->andReturn(cloudflareWorkflow('succeeded', context: [
        'registration' => ['domain_name' => 'example.test', 'expires_at' => '2027-10-05T10:00:00Z', 'status' => 'active'],
    ]));

    $result = (new CloudflareRegistrar($api))->register(cloudflareRegistration(autoRenew: true));

    expect($result['status'])->toBe('active')
        ->and($result['expires_at'])->toBe('2027-10-05T10:00:00Z');
});

it('maps a failed Cloudflare registration workflow to failed and keeps the workflow error for review', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->once()->andReturnNull();
    $api->shouldReceive('check')->once()->andReturn(cloudflareCheck());
    $api->shouldReceive('register')->once()->andReturn(cloudflareWorkflow('failed', extra: [
        'error' => ['code' => 'registry_rejected', 'message' => 'Registry rejected the registration request.'],
    ]));

    $result = (new CloudflareRegistrar($api))->register(cloudflareRegistration());

    expect($result['status'])->toBe('failed')
        ->and($result['meta']['workflow_error_code'])->toBe('registry_rejected');
});

it('never resubmits a billable registration while a Cloudflare workflow already exists', function (string $state, string $expectedStatus): void {
    $context = $state === 'succeeded'
        ? ['registration' => ['domain_name' => 'example.test', 'expires_at' => '2027-10-05T10:00:00Z']]
        : ($state === 'action_required' ? ['action' => 'registrant_email_confirmation_pending', 'confirmation_sent_to' => 'a***@example.test'] : []);
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->once()->with('example.test')->andReturn(cloudflareWorkflow($state, context: $context));
    $api->shouldNotReceive('register');
    $api->shouldNotReceive('check');

    $result = (new CloudflareRegistrar($api))->register(cloudflareRegistration());

    expect($result['status'])->toBe($expectedStatus)
        ->and($result['meta']['workflow_state'])->toBe($state);
    if ($state === 'action_required') {
        expect($result['meta']['workflow_action'])->toBe('registrant_email_confirmation_pending');
    }
})->with([
    'pending' => ['pending', 'registering'],
    'in progress' => ['in_progress', 'registering'],
    'action required' => ['action_required', 'registering'],
    'blocked' => ['blocked', 'registering'],
    'succeeded' => ['succeeded', 'active'],
]);

it('reconciles an unknown registration outcome through the workflow status instead of failing blindly', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->once()->ordered()->andReturnNull();
    $api->shouldReceive('check')->once()->andReturn(cloudflareCheck());
    $api->shouldReceive('register')->once()->andThrow(new RuntimeException('Cloudflare Registrar request failed.'));
    $api->shouldReceive('registrationStatus')->once()->ordered()->andReturn(cloudflareWorkflow('pending'));

    $result = (new CloudflareRegistrar($api))->register(cloudflareRegistration());

    expect($result['status'])->toBe('registering')
        ->and($result['meta']['workflow_state'])->toBe('pending');
});

it('rethrows the registration error when no workflow exists after a failed request', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->twice()->andReturnNull();
    $api->shouldReceive('check')->once()->andReturn(cloudflareCheck());
    $api->shouldReceive('register')->once()->andThrow(new RuntimeException('Cloudflare Registrar rejected the request.'));

    expect(fn () => (new CloudflareRegistrar($api))->register(cloudflareRegistration()))
        ->toThrow(RuntimeException::class, 'Cloudflare Registrar rejected the request.');
});

it('refuses to submit a registration that the real-time check does not allow', function (array $check): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->once()->andReturnNull();
    $api->shouldReceive('check')->once()->andReturn($check);
    $api->shouldNotReceive('register');

    expect(fn () => (new CloudflareRegistrar($api))->register(cloudflareRegistration()))
        ->toThrow(RuntimeException::class, 'not registrable');
})->with([
    'premium tier' => [cloudflareCheck(tier: 'premium')],
    'unavailable' => [cloudflareCheck(registrable: false, reason: 'domain_unavailable')],
    'not returned' => [['success' => true, 'result' => ['domains' => []]]],
]);

it('rejects undocumented or mismatched registration responses', function (array $response): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->andReturnNull();
    $api->shouldReceive('check')->once()->andReturn(cloudflareCheck());
    $api->shouldReceive('register')->once()->andReturn($response);

    expect(fn () => (new CloudflareRegistrar($api))->register(cloudflareRegistration()))
        ->toThrow(RuntimeException::class);
})->with([
    'legacy status shape' => [['success' => true, 'result' => ['id' => 'reg-1', 'status' => 'active']]],
    'other domain' => [cloudflareWorkflow('in_progress', 'other.test')],
    'unknown state' => [cloudflareWorkflow('teleported')],
    'inconsistent completed flag' => [cloudflareWorkflow('in_progress', extra: ['completed' => true])],
    'succeeded without registration' => [cloudflareWorkflow('succeeded')],
]);

it('reports premium domains as unavailable because the Registrar API cannot register them', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('check')->once()->with(['example.test'])->andReturn(cloudflareCheck(tier: 'premium'));

    $result = (new CloudflareRegistrar($api))->checkAvailability('Example.test');

    expect($result['available'])->toBeFalse()
        ->and($result['reason'])->toBe('domain_premium');
});

it('explicitly refuses renewals and does not advertise them', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldNotReceive('register');
    $registrar = new CloudflareRegistrar($api);

    expect($registrar->capabilities())->toBe(['availability_check', 'registration'])
        ->and(fn () => $registrar->renew(cloudflareRegistration()))->toThrow(CloudflareRegistrarOperationNotSupported::class);
});

it('submits registrations within the documented synchronous window and polls the workflow status endpoint', function (): void {
    configureCloudflareSettings();
    Http::preventStrayRequests();
    Http::fake([
        'api.cloudflare.com/client/v4/accounts/fixtureaccount/registrar/registrations' => Http::response(cloudflareWorkflow('pending'), 202),
        'api.cloudflare.com/client/v4/accounts/fixtureaccount/registrar/registrations/example.test/registration-status' => Http::response(cloudflareWorkflow('in_progress')),
        'api.cloudflare.com/client/v4/accounts/fixtureaccount/registrar/registrations/missing.test/registration-status' => Http::response([
            'success' => false,
            'errors' => [['code' => 10000, 'message' => 'No workflow found for missing.test']],
            'result' => null,
        ], 404),
    ]);
    $api = new HttpCloudflareApi(app(ExtensionSettingsRepository::class));

    $api->register('example.test', ['auto_renew' => false]);
    $status = $api->registrationStatus('example.test');

    expect($status['result']['state'] ?? null)->toBe('in_progress')
        ->and($api->registrationStatus('missing.test'))->toBeNull();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/accounts/fixtureaccount/registrar/registrations')
        && ! $request->hasHeader('Prefer')
        && $request->hasHeader('Authorization', 'Bearer fixture-secret-token')
        && $request->data() === ['domain_name' => 'example.test', 'auto_renew' => false]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_ends_with($request->url(), '/registrar/registrations/example.test/registration-status'));
});

it('does not follow redirects or leak the API token from registrar and DNS requests', function (): void {
    configureCloudflareSettings();
    Http::preventStrayRequests();
    Http::fake([
        'api.cloudflare.com/*' => Http::response('', 302, ['Location' => 'https://attacker.invalid/steal']),
        'attacker.invalid/*' => Http::response(['success' => true, 'result' => ['domains' => []]]),
    ]);
    $settings = app(ExtensionSettingsRepository::class);

    foreach ([
        fn () => (new HttpCloudflareApi($settings))->check(['example.test']),
        fn () => (new HttpCloudflareDnsApi($settings))->listRecords('zone-1'),
    ] as $action) {
        try {
            $action();
            $this->fail('A redirect must not be treated as success.');
        } catch (RuntimeException $exception) {
            expect($exception->getMessage())->not->toContain('fixture-secret-token');
        }
    }

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'attacker.invalid'));
});

it('wraps connection failures without exposing credentials', function (): void {
    configureCloudflareSettings();
    Http::fake(fn () => throw new ConnectionException('cURL error 28 for Bearer fixture-secret-token'));

    expect(fn () => (new HttpCloudflareApi(app(ExtensionSettingsRepository::class)))->register('example.test'))
        ->toThrow(RuntimeException::class, 'Cloudflare Registrar request failed.');
});

it('refuses registration status polling in the demo environment', function (): void {
    configureCloudflareSettings();
    app()['env'] = 'demo';
    Http::fake();

    expect(fn () => (new HttpCloudflareApi(app(ExtensionSettingsRepository::class)))->registrationStatus('example.test'))
        ->toThrow(RuntimeException::class, 'disabled in the demo environment');
    Http::assertNothingSent();
});

it('finds zones with a documented page size and creates full zones with the documented body', function (): void {
    configureCloudflareSettings();
    Http::preventStrayRequests();
    Http::fake([
        'api.cloudflare.com/client/v4/zones?*' => Http::response(['success' => true, 'result' => [
            ['id' => 'zone-other', 'name' => 'sub.example.test', 'account' => ['id' => 'fixtureaccount']],
        ]]),
        'api.cloudflare.com/client/v4/zones' => Http::response(['success' => true, 'result' => [
            'id' => 'zone-new', 'name' => 'example.test', 'status' => 'pending', 'name_servers' => ['a.ns.cloudflare.com'],
        ]]),
    ]);

    $zone = (new HttpCloudflareDnsApi(app(ExtensionSettingsRepository::class)))->findOrCreateZone('example.test');

    expect($zone['id'])->toBe('zone-new');
    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'GET') {
            return false;
        }
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['name'] ?? null) === 'example.test'
            && ($query['account_id'] ?? $query['account.id'] ?? null) === 'fixtureaccount'
            && (int) ($query['per_page'] ?? 0) >= 5
            && (int) ($query['per_page'] ?? 0) <= 50;
    });
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->data() === ['name' => 'example.test', 'account' => ['id' => 'fixtureaccount'], 'type' => 'full']);
});

it('lists every DNS record page instead of truncating at the first page', function (): void {
    configureCloudflareSettings();
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $page = (int) ($query['page'] ?? 1);

        return Http::response([
            'success' => true,
            'result' => [['id' => 'record-'.$page, 'type' => 'A', 'name' => 'example.test', 'content' => '192.0.2.'.$page]],
            'result_info' => ['page' => $page, 'per_page' => 1, 'total_pages' => 3, 'count' => 1, 'total_count' => 3],
        ]);
    });

    $records = (new HttpCloudflareDnsApi(app(ExtensionSettingsRepository::class)))->listRecords('zone-1');

    expect(array_column($records, 'id'))->toBe(['record-1', 'record-2', 'record-3']);
    Http::assertSentCount(3);
});

it('advertises the record capability the domains module uses so DNS CRUD is reachable', function (): void {
    $api = Mockery::mock(CloudflareDnsApi::class);
    $api->shouldReceive('listRecords')->once()->with('zone-1')->andReturn([['id' => 'record-1']]);
    app(DomainDnsProviderRegistry::class)->register(new CloudflareDnsProvider($api));
    $registration = new DomainRegistration([
        'domain_name' => 'example.test',
        'dns_provider_key' => 'cloudflare-dns',
        'meta' => ['dns_zone' => ['zone_reference' => 'zone-1']],
    ]);

    expect(app(DomainService::class)->dnsRecords($registration))->toBe([['id' => 'record-1']]);
});

it('sends complete record names, MX priority and proxy flags only where Cloudflare documents them', function (): void {
    $api = Mockery::mock(CloudflareDnsApi::class);
    $api->shouldReceive('createRecord')->once()->with('zone-1', [
        'type' => 'A', 'name' => 'example.test', 'content' => '192.0.2.10', 'ttl' => 1, 'proxied' => true,
    ])->andReturn(['id' => 'r1']);
    $api->shouldReceive('createRecord')->once()->with('zone-1', [
        'type' => 'TXT', 'name' => 'www.example.test', 'content' => 'hello', 'ttl' => 3600, 'proxied' => false,
    ])->andReturn(['id' => 'r2']);
    $api->shouldReceive('createRecord')->once()->with('zone-1', [
        'type' => 'MX', 'name' => 'example.test', 'content' => 'mail.example.test', 'ttl' => 3600, 'proxied' => false, 'priority' => 10,
    ])->andReturn(['id' => 'r3']);
    $provider = new CloudflareDnsProvider($api);
    $registration = new DomainRegistration([
        'domain_name' => 'example.test',
        'meta' => ['dns_zone' => ['zone_reference' => 'zone-1']],
    ]);

    $provider->upsertRecord($registration, ['type' => 'A', 'name' => '@', 'content' => '192.0.2.10', 'ttl' => 1, 'proxied' => true]);
    $provider->upsertRecord($registration, ['type' => 'TXT', 'name' => 'www', 'content' => 'hello', 'proxied' => true]);
    $provider->upsertRecord($registration, ['type' => 'MX', 'name' => 'example.test.', 'content' => 'mail.example.test', 'priority' => 10]);

    expect(fn () => $provider->upsertRecord($registration, ['type' => 'MX', 'name' => '@', 'content' => 'mail.example.test']))
        ->toThrow(InvalidArgumentException::class, 'priority')
        ->and(fn () => $provider->upsertRecord($registration, ['type' => 'SRV', 'name' => '_sip._tcp', 'content' => '10 5 5060 sip.example.test']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $provider->upsertRecord($registration, ['type' => 'CAA', 'name' => '@', 'content' => '0 issue "ca.example"']))
        ->toThrow(InvalidArgumentException::class);
});

it('refreshes a Cloudflare registration that is still registering without submitting a second registration', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->once()->with('example.test')->andReturn(
        cloudflareWorkflow('succeeded', context: ['registration' => ['domain_name' => 'example.test', 'expires_at' => '2027-10-05T10:00:00Z']]),
    );
    $api->shouldNotReceive('register');
    $api->shouldNotReceive('check');
    app(DomainRegistrarRegistry::class)->register(new CloudflareRegistrar($api));
    $registering = DomainRegistration::query()->create([
        'number' => 'DOM-CFREFRESH1',
        'domain_name' => 'example.test',
        'registrar_key' => 'cloudflare-registrar',
        'status' => 'registering',
    ]);
    $active = DomainRegistration::query()->create([
        'number' => 'DOM-CFREFRESH2',
        'domain_name' => 'other.test',
        'registrar_key' => 'cloudflare-registrar',
        'status' => 'active',
    ]);

    $this->artisan('agovena:refresh-domain-registrations')->assertSuccessful();

    expect($registering->fresh()->status->value)->toBe('active')
        ->and($registering->fresh()->expires_at?->toDateString())->toBe('2027-10-05')
        ->and($registering->fresh()->registered_at)->not->toBeNull()
        ->and($active->fresh()->status->value)->toBe('active');
});

it('keeps a registering Cloudflare domain registering when the status refresh fails', function (): void {
    $api = Mockery::mock(CloudflareApi::class);
    $api->shouldReceive('registrationStatus')->once()->andThrow(new RuntimeException('Cloudflare Registrar request failed.'));
    $api->shouldNotReceive('register');
    app(DomainRegistrarRegistry::class)->register(new CloudflareRegistrar($api));
    $registering = DomainRegistration::query()->create([
        'number' => 'DOM-CFREFRESH3',
        'domain_name' => 'example.test',
        'registrar_key' => 'cloudflare-registrar',
        'status' => 'registering',
    ]);

    $this->artisan('agovena:refresh-domain-registrations')->assertSuccessful();

    expect($registering->fresh()->status->value)->toBe('registering')
        ->and($registering->fresh()->failed_at)->toBeNull();
});
