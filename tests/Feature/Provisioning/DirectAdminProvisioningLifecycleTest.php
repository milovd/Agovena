<?php

declare(strict_types=1);

use Agovena\Extensions\DirectAdmin\DirectAdminAccount;
use Agovena\Extensions\DirectAdmin\DirectAdminProvisioner;
use Agovena\Extensions\DirectAdmin\DirectAdminUsernameGenerator;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

const DIRECTADMIN_TEST_URL = 'https://da.example.test:2222';
const DIRECTADMIN_TEST_TOKEN = 'da-login-key-SECRET-7f3a';

function enableDirectAdmin(?DirectAdminUsernameGenerator $generator = null): DirectAdminProvisioner
{
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    app(ExtensionManager::class)->discover();
    installAndEnableExtension('directadmin');
    if ($generator !== null) {
        app()->instance(DirectAdminUsernameGenerator::class, $generator);
    }
    app()->forgetInstance(DirectAdminProvisioner::class);
    app(ExtensionManager::class)->rebuildRuntime();

    $settings = app(ExtensionSettingsRepository::class);
    foreach (directAdminServerSettings() as $key => $value) {
        $settings->set('directadmin', $key, $value, secret: $key === 'api_token');
    }

    return app(DirectAdminProvisioner::class);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function directAdminServerSettings(array $overrides = []): array
{
    return array_merge([
        'api_url' => DIRECTADMIN_TEST_URL,
        'api_username' => 'agovena',
        'api_token' => DIRECTADMIN_TEST_TOKEN,
        'ip' => '198.51.100.20',
        'verify_tls' => true,
        'timeout' => '20',
    ], $overrides);
}

function fixedDirectAdminUsername(string $username): DirectAdminUsernameGenerator
{
    return new class($username) extends DirectAdminUsernameGenerator
    {
        public function __construct(private readonly string $fixed) {}

        public function generate(): string
        {
            return $this->fixed;
        }
    };
}

/** @param array<string, mixed> $meta */
function directAdminInstance(array $meta = [], ?string $externalRef = null): ServiceInstance
{
    static $sequence = 0;
    $sequence++;

    return ServiceInstance::query()->create([
        'number' => 'SRV-DA-'.$sequence.'-'.bin2hex(random_bytes(3)),
        'customer_email' => 'buyer'.$sequence.'@example.com',
        'customer_name' => 'Buyer',
        'status' => 'provisioning',
        'provider_key' => 'directadmin',
        'external_ref' => $externalRef,
        'meta' => $meta,
    ]);
}

/**
 * @param  array<string, mixed>|null  $server
 * @param  array<string, mixed>|null  $provider
 * @param  array<string, mixed>  $meta
 */
function directAdminInfo(ServiceInstance $instance, ?array $server = null, ?array $provider = null, array $meta = []): ServiceInstanceInfo
{
    return new ServiceInstanceInfo(
        id: $instance->id,
        label: (string) $instance->number,
        status: 'provisioning',
        providerKey: 'directadmin',
        externalRef: $instance->external_ref,
        meta: $meta,
        serverSettings: $server ?? directAdminServerSettings(),
        providerSettings: $provider ?? ['domain' => 'example.com', 'package' => 'starter'],
    );
}

/**
 * Route-scripted fake: each "METHOD /path" key holds a queue of responses or callables.
 *
 * @param  array<string, list<mixed>>  $routes
 */
function fakeDirectAdmin(array $routes): void
{
    $queue = new ArrayObject($routes);
    Http::swap(new Factory(app('events')));
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($queue) {
        $key = $request->method().' '.parse_url($request->url(), PHP_URL_PATH);
        $responses = $queue[$key] ?? [];
        if ($responses === []) {
            return Http::response('unexpected '.$key, 599);
        }
        $next = array_shift($responses);
        $queue[$key] = $responses;

        return is_callable($next) ? $next($request) : $next;
    });
}

function daOk(string $text = 'Success'): mixed
{
    return Http::response('error=0&text='.rawurlencode($text).'&details=', 200, ['Content-Type' => 'text/plain']);
}

function daError(string $text = 'Unable to comply'): mixed
{
    return Http::response('error=1&text='.rawurlencode($text).'&details=Reason', 200, ['Content-Type' => 'text/plain']);
}

/** @param list<string> $values */
function daList(array $values): mixed
{
    return Http::response(implode('&', array_map(static fn (string $value): string => 'list[]='.rawurlencode($value), $values)), 200, ['Content-Type' => 'text/plain']);
}

/** @param array<string, string> $overrides */
function daUserConfig(string $username, array $overrides = []): mixed
{
    return Http::response(http_build_query(array_merge([
        'account' => 'ON',
        'creator' => 'agovena',
        'domain' => 'example.com',
        'package' => 'starter',
        'suspended' => 'no',
        'username' => $username,
        'usertype' => 'user',
    ], $overrides)), 200, ['Content-Type' => 'text/plain']);
}

function hasDirectAdminAuth(Request $request): bool
{
    return $request->hasHeader('Authorization', 'Basic '.base64_encode('agovena:'.DIRECTADMIN_TEST_TOKEN));
}

/** @return list<Request> */
function directAdminRequests(string $method, string $path): array
{
    return Http::recorded(fn (Request $request) => $request->method() === $method
        && parse_url($request->url(), PHP_URL_PATH) === $path)
        ->map(fn (array $pair) => $pair[0])
        ->values()
        ->all();
}

function directAdminClaim(
    ServiceInstance $instance,
    string $username = 'agoact01',
    string $state = 'active',
    string $endpoint = DIRECTADMIN_TEST_URL,
    string $domain = 'example.com',
): DirectAdminAccount {
    $id = DB::table('directadmin_accounts')->insertGetId([
        'service_instance_id' => $instance->id,
        'endpoint' => $endpoint,
        'remote_id' => $username,
        'remote_name' => $domain,
        'plan' => 'starter',
        'state' => $state,
        'revision' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DirectAdminAccount::query()->findOrFail($id);
}

function directAdminClaimOf(ServiceInstance $instance): ?DirectAdminAccount
{
    return DirectAdminAccount::query()->where('service_instance_id', $instance->id)->first();
}

function expectDirectAdminRefusal(callable $callback): void
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('instance');

        return;
    }

    test()->fail('Expected a ValidationException.');
}

test('directadmin migration creates the claim table with unique identity columns', function () {
    enableDirectAdmin();

    expect(Schema::hasColumns('directadmin_accounts', [
        'id', 'service_instance_id', 'endpoint', 'remote_id', 'remote_name', 'plan', 'state', 'revision', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $one = directAdminInstance();
    $two = directAdminInstance();
    directAdminClaim($one, 'agodup01', domain: 'one.example');

    expect(fn () => directAdminClaim($two, 'agodup01', domain: 'two.example'))->toThrow(QueryException::class)
        ->and(fn () => directAdminClaim($two, 'agodup02', domain: 'one.example'))->toThrow(QueryException::class)
        ->and(fn () => directAdminClaim($one, 'agodup03', domain: 'three.example'))->toThrow(QueryException::class);
});

test('directadmin happy create sends the documented legacy request and a retry is idempotent', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    fakeDirectAdmin(['POST /CMD_API_ACCOUNT_USER' => [daOk('User created')]]);

    $provisioner->provision(directAdminInfo($instance));
    $provisioner->provision(directAdminInfo($instance));

    $account = directAdminClaimOf($instance);
    $requests = directAdminRequests('POST', '/CMD_API_ACCOUNT_USER');
    expect($requests)->toHaveCount(1)
        ->and(Http::recorded())->toHaveCount(1)
        ->and($account->state)->toBe('active')
        ->and($account->revision)->toBe(1)
        ->and($account->endpoint)->toBe(DIRECTADMIN_TEST_URL)
        ->and($account->remote_id)->toMatch('/\A[a-z][a-z0-9]{7}\z/')
        ->and($account->remote_name)->toBe('example.com')
        ->and($account->plan)->toBe('starter');

    $request = $requests[0];
    $data = $request->data();
    expect($request->url())->toBe(DIRECTADMIN_TEST_URL.'/CMD_API_ACCOUNT_USER')
        ->and(hasDirectAdminAuth($request))->toBeTrue()
        ->and($request->isForm())->toBeTrue()
        ->and(array_keys($data))->toEqualCanonicalizing(['action', 'add', 'username', 'email', 'passwd', 'passwd2', 'domain', 'package', 'ip', 'notify'])
        ->and($data['action'])->toBe('create')
        ->and($data['add'])->toBe('Submit')
        ->and($data['username'])->toBe($account->remote_id)
        ->and($data['email'])->toBe($instance->customer_email)
        ->and($data['domain'])->toBe('example.com')
        ->and($data['package'])->toBe('starter')
        ->and($data['ip'])->toBe('198.51.100.20')
        ->and($data['notify'])->toBe('yes')
        ->and(strlen((string) $data['passwd']))->toBe(24)
        ->and($data['passwd2'])->toBe($data['passwd']);
});

test('directadmin username generator yields provider-valid random usernames', function () {
    $generator = new DirectAdminUsernameGenerator;
    $usernames = array_map(static fn (): string => $generator->generate(), range(1, 20));

    foreach ($usernames as $username) {
        expect($username)->toMatch('/\A[a-z][a-z0-9]{7}\z/');
    }
    expect(array_unique($usernames))->toHaveCount(20);
});

test('two services cannot claim the same directadmin username or domain and make no remote call', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agoshare'));
    $owner = directAdminInstance();
    $sameUsername = directAdminInstance();
    $sameDomain = directAdminInstance();
    directAdminClaim($owner, 'agoshare', domain: 'owner.example');
    fakeDirectAdmin([]);

    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($sameUsername, provider: ['domain' => 'other.example', 'package' => 'starter'])));
    app()->instance(DirectAdminUsernameGenerator::class, fixedDirectAdminUsername('agofresh'));
    app()->forgetInstance(DirectAdminProvisioner::class);
    expectDirectAdminRefusal(fn () => app(DirectAdminProvisioner::class)->provision(directAdminInfo($sameDomain, provider: ['domain' => 'owner.example', 'package' => 'starter'])));

    Http::assertNothingSent();
    expect(directAdminClaimOf($sameUsername))->toBeNull()
        ->and(directAdminClaimOf($sameDomain))->toBeNull()
        ->and(directAdminClaimOf($owner)?->state)->toBe('active');
});

test('directadmin create timeout is never blindly retried and reconciles by the stored username', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agotime1'));
    $instance = directAdminInstance();
    fakeDirectAdmin(['POST /CMD_API_ACCOUNT_USER' => [fn () => Http::failedConnection()]]);

    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));
    expect(directAdminClaimOf($instance)?->state)->toBe('unknown');

    fakeDirectAdmin([
        'GET /CMD_API_SHOW_USERS' => [daList(['someone1', 'agotime1'])],
        'GET /CMD_API_SHOW_USER_CONFIG' => [daUserConfig('agotime1')],
    ]);
    $provisioner->provision(directAdminInfo($instance));

    $read = directAdminRequests('GET', '/CMD_API_SHOW_USER_CONFIG');
    expect(directAdminRequests('POST', '/CMD_API_ACCOUNT_USER'))->toHaveCount(0)
        ->and($read)->toHaveCount(1)
        ->and($read[0]->url())->toBe(DIRECTADMIN_TEST_URL.'/CMD_API_SHOW_USER_CONFIG?user=agotime1')
        ->and(hasDirectAdminAuth($read[0]))->toBeTrue()
        ->and(directAdminClaimOf($instance)?->state)->toBe('active');
});

test('directadmin reconciliation recreates with the same username only when the user is absent', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agoabsn1'));
    $instance = directAdminInstance();
    fakeDirectAdmin(['POST /CMD_API_ACCOUNT_USER' => [fn () => Http::failedConnection()]]);
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));

    fakeDirectAdmin([
        'GET /CMD_API_SHOW_USERS' => [daList(['someone1'])],
        'POST /CMD_API_ACCOUNT_USER' => [daOk()],
    ]);
    $provisioner->provision(directAdminInfo($instance));

    $retry = directAdminRequests('POST', '/CMD_API_ACCOUNT_USER');
    expect($retry)->toHaveCount(1)
        ->and($retry[0]->data()['username'])->toBe('agoabsn1')
        ->and(directAdminRequests('GET', '/CMD_API_SHOW_USER_CONFIG'))->toHaveCount(0)
        ->and(directAdminClaimOf($instance)?->state)->toBe('active');
});

test('directadmin ambiguous reconciliation keeps the claim unknown and never creates again', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agoambi1'));
    $instance = directAdminInstance();
    fakeDirectAdmin(['POST /CMD_API_ACCOUNT_USER' => [Http::response('<html>gateway</html>', 502)]]);
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));

    fakeDirectAdmin([
        'GET /CMD_API_SHOW_USERS' => [Http::response('<html>busy</html>', 503), Http::response('<html>login</html>', 200)],
    ]);
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));

    expect(directAdminRequests('POST', '/CMD_API_ACCOUNT_USER'))->toHaveCount(0)
        ->and(directAdminClaimOf($instance)?->state)->toBe('unknown');
});

test('directadmin reconciliation refuses an existing user that does not match the claim', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agoforgn'));
    $instance = directAdminInstance();
    fakeDirectAdmin(['POST /CMD_API_ACCOUNT_USER' => [fn () => Http::failedConnection()]]);
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));

    fakeDirectAdmin([
        'GET /CMD_API_SHOW_USERS' => [daList(['agoforgn']), daList(['agoforgn'])],
        'GET /CMD_API_SHOW_USER_CONFIG' => [
            daUserConfig('agoforgn', ['creator' => 'otherreseller']),
            daUserConfig('agoforgn', ['domain' => 'victim.example']),
        ],
    ]);
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));

    expect(directAdminRequests('POST', '/CMD_API_ACCOUNT_USER'))->toHaveCount(0)
        ->and(directAdminClaimOf($instance)?->state)->toBe('unknown');
});

test('directadmin error flag with http 200 is not marked active and never adopts afterwards', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agofail1'));
    $instance = directAdminInstance();
    fakeDirectAdmin(['POST /CMD_API_ACCOUNT_USER' => [daError('That username already exists')]]);

    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));
    expect(directAdminClaimOf($instance)?->state)->toBe('failed');

    fakeDirectAdmin([
        'GET /CMD_API_SHOW_USERS' => [daList(['agofail1'])],
        'GET /CMD_API_SHOW_USER_CONFIG' => [daUserConfig('agofail1')],
    ]);
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));

    expect(directAdminRequests('POST', '/CMD_API_ACCOUNT_USER'))->toHaveCount(0)
        ->and(directAdminClaimOf($instance)?->state)->toBe('failed');
});

test('directadmin malformed or redirected create responses are treated as ambiguous', function () {
    $provisioner = enableDirectAdmin();
    $html = directAdminInstance();
    $redirect = directAdminInstance();
    fakeDirectAdmin(['POST /CMD_API_ACCOUNT_USER' => [
        Http::response('<html>login</html>', 200),
        Http::response('', 302, ['Location' => 'https://evil.example/CMD_LOGIN']),
    ]]);

    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($html)));
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($redirect, provider: ['domain' => 'redirect.example', 'package' => 'starter'])));

    expect(directAdminClaimOf($html)?->state)->toBe('unknown')
        ->and(directAdminClaimOf($redirect)?->state)->toBe('unknown');
    Http::assertSentCount(2);
});

test('directadmin create refuses invalid product or server settings before any claim or call', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    fakeDirectAdmin([]);

    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance, provider: ['domain' => 'example.com'])));
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance, provider: ['package' => 'starter', 'domain' => 'not a domain'])));
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance, server: directAdminServerSettings(['ip' => '']))));
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance, server: directAdminServerSettings(['api_token' => '']))));
    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance, server: directAdminServerSettings(['api_url' => 'https://da.example.test:2222/api']))));

    Http::assertNothingSent();
    expect(directAdminClaimOf($instance))->toBeNull();
});

test('directadmin suspend uses the documented non-toggle command and is idempotent', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agosusp1');
    fakeDirectAdmin(['POST /CMD_API_SELECT_USERS' => [daOk()]]);

    $provisioner->suspend(directAdminInfo($instance));
    $provisioner->suspend(directAdminInfo($instance));

    $requests = directAdminRequests('POST', '/CMD_API_SELECT_USERS');
    $account = directAdminClaimOf($instance);
    expect($requests)->toHaveCount(1)
        ->and($requests[0]->url())->toBe(DIRECTADMIN_TEST_URL.'/CMD_API_SELECT_USERS')
        ->and(hasDirectAdminAuth($requests[0]))->toBeTrue()
        ->and($requests[0]->isForm())->toBeTrue()
        ->and($requests[0]->data())->toBe(['dosuspend' => 'yes', 'select0' => 'agosusp1'])
        ->and($account->state)->toBe('suspended')
        ->and($account->revision)->toBe(4);
});

test('directadmin suspend failure flag or redirect leaves the account active', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agosusp2');
    fakeDirectAdmin(['POST /CMD_API_SELECT_USERS' => [daError(), Http::response('', 302, ['Location' => '/CMD_SELECT_USERS'])]]);

    expectDirectAdminRefusal(fn () => $provisioner->suspend(directAdminInfo($instance)));
    expectDirectAdminRefusal(fn () => $provisioner->suspend(directAdminInfo($instance)));

    Http::assertSentCount(2);
    expect(directAdminClaimOf($instance)?->state)->toBe('active');
});

test('directadmin unsuspend uses the documented non-toggle command', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agouns01', 'suspended');
    fakeDirectAdmin(['POST /CMD_API_SELECT_USERS' => [daOk()]]);

    $provisioner->unsuspend(directAdminInfo($instance));
    $provisioner->unsuspend(directAdminInfo($instance));

    $requests = directAdminRequests('POST', '/CMD_API_SELECT_USERS');
    expect($requests)->toHaveCount(1)
        ->and($requests[0]->data())->toBe(['dounsuspend' => 'yes', 'select0' => 'agouns01'])
        ->and(directAdminClaimOf($instance)?->state)->toBe('active');
});

test('directadmin unsuspend malformed response leaves the account suspended', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agouns02', 'suspended');
    fakeDirectAdmin(['POST /CMD_API_SELECT_USERS' => [Http::response('<html></html>', 200), Http::response('text=done', 200)]]);

    expectDirectAdminRefusal(fn () => $provisioner->unsuspend(directAdminInfo($instance)));
    expectDirectAdminRefusal(fn () => $provisioner->unsuspend(directAdminInfo($instance)));

    expect(directAdminClaimOf($instance)?->state)->toBe('suspended');
});

test('directadmin terminate deletes the owned user and keeps the claim row', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agoterm1', 'suspended');
    fakeDirectAdmin(['POST /CMD_API_SELECT_USERS' => [daOk()]]);

    $provisioner->terminate(directAdminInfo($instance));
    $provisioner->terminate(directAdminInfo($instance));

    $requests = directAdminRequests('POST', '/CMD_API_SELECT_USERS');
    $account = directAdminClaimOf($instance);
    expect($requests)->toHaveCount(1)
        ->and($requests[0]->data())->toBe(['confirmed' => 'Confirm', 'delete' => 'yes', 'select0' => 'agoterm1'])
        ->and($account->state)->toBe('terminated')
        ->and($account->remote_id)->toBe('agoterm1');
});

test('directadmin terminate failure keeps the account claimed and active', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agoterm2');
    fakeDirectAdmin(['POST /CMD_API_SELECT_USERS' => [daError()]]);

    expectDirectAdminRefusal(fn () => $provisioner->terminate(directAdminInfo($instance)));

    expect(directAdminClaimOf($instance)?->state)->toBe('active');
});

test('directadmin terminate never deletes an unconfirmed creation and closes a failed claim locally', function () {
    $provisioner = enableDirectAdmin();
    $unknown = directAdminInstance();
    $failed = directAdminInstance();
    directAdminClaim($unknown, 'agoterm3', 'unknown', domain: 'unknown.example');
    directAdminClaim($failed, 'agoterm4', 'failed', domain: 'failed.example');
    fakeDirectAdmin([]);

    expectDirectAdminRefusal(fn () => $provisioner->terminate(directAdminInfo($unknown)));
    $provisioner->terminate(directAdminInfo($failed));

    Http::assertNothingSent();
    expect(directAdminClaimOf($unknown)?->state)->toBe('unknown')
        ->and(directAdminClaimOf($failed)?->state)->toBe('terminated');
});

test('directadmin change plan assigns an existing package and verifies it', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agoplan1');
    fakeDirectAdmin([
        'GET /CMD_API_PACKAGES_USER' => [daList(['starter', 'pro'])],
        'POST /CMD_API_SELECT_USERS' => [daOk()],
        'GET /CMD_API_SHOW_USER_CONFIG' => [daUserConfig('agoplan1', ['package' => 'pro'])],
    ]);

    $provisioner->changePlan(directAdminInfo($instance), ['id' => '9', 'provider_settings' => ['package' => 'pro', 'domain' => 'example.com']]);

    $requests = directAdminRequests('POST', '/CMD_API_SELECT_USERS');
    $account = directAdminClaimOf($instance);
    expect(directAdminRequests('GET', '/CMD_API_PACKAGES_USER')[0]->url())->toBe(DIRECTADMIN_TEST_URL.'/CMD_API_PACKAGES_USER')
        ->and($requests)->toHaveCount(1)
        ->and(hasDirectAdminAuth($requests[0]))->toBeTrue()
        ->and($requests[0]->data())->toBe(['dopackage' => 'yes', 'package' => 'pro', 'select0' => 'agoplan1'])
        ->and($account->plan)->toBe('pro')
        ->and($account->state)->toBe('active')
        ->and($account->revision)->toBe(4);
});

test('directadmin change plan failures keep the previous plan', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agoplan2');
    fakeDirectAdmin([
        'GET /CMD_API_PACKAGES_USER' => [daList(['starter']), daList(['starter', 'pro']), daList(['starter', 'pro'])],
        'POST /CMD_API_SELECT_USERS' => [daError(), daOk()],
        'GET /CMD_API_SHOW_USER_CONFIG' => [daUserConfig('agoplan2', ['package' => 'starter'])],
    ]);

    expectDirectAdminRefusal(fn () => $provisioner->changePlan(directAdminInfo($instance), 'pro'));
    expect(directAdminRequests('POST', '/CMD_API_SELECT_USERS'))->toHaveCount(0);
    expectDirectAdminRefusal(fn () => $provisioner->changePlan(directAdminInfo($instance), 'pro'));
    expectDirectAdminRefusal(fn () => $provisioner->changePlan(directAdminInfo($instance), 'pro'));
    expectDirectAdminRefusal(fn () => $provisioner->changePlan(directAdminInfo($instance), ['id' => '9', 'provider_settings' => []]));

    expect(directAdminRequests('POST', '/CMD_API_SELECT_USERS'))->toHaveCount(2)
        ->and(directAdminClaimOf($instance)?->plan)->toBe('starter');
});

test('directadmin sync status maps the documented suspended field', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agosync1');
    fakeDirectAdmin([
        'GET /CMD_API_SHOW_USER_CONFIG' => [daUserConfig('agosync1', ['suspended' => 'yes']), daUserConfig('agosync1')],
    ]);

    $suspended = $provisioner->syncStatus(directAdminInfo($instance));
    expect($suspended->status)->toBe('suspended')
        ->and($suspended->externalRef)->toBe('agosync1')
        ->and($suspended->meta['provider_mapping']['provider_id'] ?? null)->toBe('agosync1')
        ->and(directAdminClaimOf($instance)?->state)->toBe('suspended');

    $active = $provisioner->poll(directAdminInfo($instance));
    expect($active->status)->toBe('active')
        ->and(directAdminClaimOf($instance)?->state)->toBe('active');
});

test('directadmin sync status fails closed on malformed or missing users', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agosync2');
    fakeDirectAdmin([
        'GET /CMD_API_SHOW_USER_CONFIG' => [
            daUserConfig('agosync2', ['suspended' => 'maybe']),
            daUserConfig('agosync9'),
            daUserConfig('agosync2', ['domain' => 'other.example']),
            daError('Unable to find user'),
            Http::response('error=0', 500),
        ],
    ]);

    foreach (range(1, 5) as $attempt) {
        expectDirectAdminRefusal(fn () => $provisioner->syncStatus(directAdminInfo($instance)));
    }

    expect(directAdminClaimOf($instance)?->state)->toBe('active');
});

test('directadmin sync status reconciles an unconfirmed creation by the stored username only', function () {
    $provisioner = enableDirectAdmin();
    $absent = directAdminInstance();
    $present = directAdminInstance();
    directAdminClaim($absent, 'agoabs01', 'unknown', domain: 'absent.example');
    directAdminClaim($present, 'agopre01', 'creating', domain: 'present.example');
    fakeDirectAdmin([
        'GET /CMD_API_SHOW_USERS' => [daList(['agopre01']), daList(['agopre01'])],
        'GET /CMD_API_SHOW_USER_CONFIG' => [daUserConfig('agopre01', ['domain' => 'present.example'])],
    ]);

    $absentInfo = $provisioner->syncStatus(directAdminInfo($absent, meta: ['provider_mapping' => ['provider_id' => 'agoabs01']]));
    $presentInfo = $provisioner->syncStatus(directAdminInfo($present));

    expect($absentInfo->status)->toBe('provisioning')
        ->and($absentInfo->meta['provider_reconciliation'] ?? null)->toBe('absent')
        ->and($absentInfo->meta)->not->toHaveKey('provider_mapping')
        ->and(directAdminClaimOf($absent)?->state)->toBe('unknown')
        ->and($presentInfo->status)->toBe('active')
        ->and($presentInfo->meta['provider_mapping']['provider_id'] ?? null)->toBe('agopre01')
        ->and(directAdminClaimOf($present)?->state)->toBe('active');
});

test('directadmin lifecycle is refused without remote calls when the endpoint is re-pointed', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agomove1');
    fakeDirectAdmin([]);
    $moved = directAdminServerSettings(['api_url' => 'https://other-da.example.test:2222']);

    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance, $moved)));
    expectDirectAdminRefusal(fn () => $provisioner->suspend(directAdminInfo($instance, $moved)));
    expectDirectAdminRefusal(fn () => $provisioner->unsuspend(directAdminInfo($instance, $moved)));
    expectDirectAdminRefusal(fn () => $provisioner->terminate(directAdminInfo($instance, $moved)));
    expectDirectAdminRefusal(fn () => $provisioner->changePlan(directAdminInfo($instance, $moved), 'pro'));
    expectDirectAdminRefusal(fn () => $provisioner->syncStatus(directAdminInfo($instance, $moved)));

    Http::assertNothingSent();
    expect(directAdminClaimOf($instance)?->state)->toBe('active');
});

test('directadmin endpoint identity normalizes the default port and ignores credentials', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agoport1', 'active', 'https://da.example.test:443');
    fakeDirectAdmin(['GET /CMD_API_SHOW_USER_CONFIG' => [daUserConfig('agoport1')]]);

    $info = $provisioner->syncStatus(directAdminInfo($instance, directAdminServerSettings([
        'api_url' => 'HTTPS://DA.example.test/',
        'api_token' => 'a-rotated-login-key',
    ])));

    expect($info->status)->toBe('active');
    Http::assertSentCount(1);
});

test('directadmin hand written provider mapping without a claim row is refused without network', function () {
    $provisioner = enableDirectAdmin();
    $meta = ['provider_mapping' => ['provider_id' => 'victim01', 'username' => 'victim01']];
    $instance = directAdminInstance($meta, 'victim01');
    fakeDirectAdmin([]);
    $info = directAdminInfo($instance, meta: $meta);

    expectDirectAdminRefusal(fn () => $provisioner->suspend($info));
    expectDirectAdminRefusal(fn () => $provisioner->unsuspend($info));
    expectDirectAdminRefusal(fn () => $provisioner->terminate($info));
    expectDirectAdminRefusal(fn () => $provisioner->changePlan($info, 'pro'));
    $synced = $provisioner->syncStatus($info);

    Http::assertNothingSent();
    expect($synced->meta)->not->toHaveKey('provider_mapping')
        ->and($synced->meta['provider_reconciliation'] ?? null)->toBe('absent')
        ->and($synced->externalRef)->toBeNull()
        ->and($provisioner->actions($info))->toBe([])
        ->and($provisioner->panel($info))->toBeNull()
        ->and(DirectAdminAccount::query()->count())->toBe(0);
});

test('directadmin product settings cannot choose or adopt a username', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agonew01'));
    $instance = directAdminInstance();
    fakeDirectAdmin(['POST /CMD_API_ACCOUNT_USER' => [daOk()]]);

    $provisioner->provision(directAdminInfo($instance, provider: ['domain' => 'example.com', 'package' => 'starter', 'username' => 'victim01']));

    expect(directAdminRequests('POST', '/CMD_API_ACCOUNT_USER')[0]->data()['username'])->toBe('agonew01')
        ->and(Http::recorded())->toHaveCount(1)
        ->and(collect($provisioner->productSettings())->pluck('key')->all())->toBe(['package', 'domain']);
});

test('directadmin stale revision never overwrites a concurrently changed claim', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agostal1'));
    $instance = directAdminInstance();
    fakeDirectAdmin([
        'POST /CMD_API_ACCOUNT_USER' => [function () use ($instance) {
            DB::table('directadmin_accounts')
                ->where('service_instance_id', $instance->id)
                ->update(['state' => 'unknown', 'revision' => 7]);

            return daOk();
        }],
    ]);

    expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));

    $account = directAdminClaimOf($instance);
    expect($account->state)->toBe('unknown')
        ->and($account->revision)->toBe(7);
});

test('directadmin stale revision during suspend is not overwritten', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agostal2');
    fakeDirectAdmin([
        'POST /CMD_API_SELECT_USERS' => [function () use ($instance) {
            DB::table('directadmin_accounts')
                ->where('service_instance_id', $instance->id)
                ->update(['revision' => 9, 'state' => 'terminated']);

            return daOk();
        }],
    ]);

    expectDirectAdminRefusal(fn () => $provisioner->suspend(directAdminInfo($instance)));

    $account = directAdminClaimOf($instance);
    expect($account->state)->toBe('terminated')
        ->and($account->revision)->toBe(9);
});

test('directadmin refuses concurrent lifecycle work while the account lock is held', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    fakeDirectAdmin([]);
    $lock = Cache::lock('directadmin:account:'.$instance->id, 120);
    $lock->get();

    try {
        expectDirectAdminRefusal(fn () => $provisioner->provision(directAdminInfo($instance)));
    } finally {
        $lock->release();
    }

    Http::assertNothingSent();
    expect(directAdminClaimOf($instance))->toBeNull();
});

test('directadmin never persists or exposes the login key or generated password', function () {
    $provisioner = enableDirectAdmin(fixedDirectAdminUsername('agosecr1'));
    $instance = directAdminInstance();
    fakeDirectAdmin([
        'POST /CMD_API_ACCOUNT_USER' => [daOk()],
        'GET /CMD_API_SHOW_USER_CONFIG' => [daUserConfig('agosecr1'), Http::response('error=1&text='.DIRECTADMIN_TEST_TOKEN, 500)],
    ]);

    $provisioner->provision(directAdminInfo($instance));
    $password = (string) directAdminRequests('POST', '/CMD_API_ACCOUNT_USER')[0]->data()['passwd'];
    $info = $provisioner->syncStatus(directAdminInfo($instance));

    $messages = '';
    try {
        $provisioner->syncStatus(directAdminInfo($instance));
    } catch (ValidationException $exception) {
        $messages = json_encode($exception->errors()).$exception->getMessage();
    }

    $persisted = json_encode([
        DB::table('directadmin_accounts')->get()->all(),
        DB::table('service_instances')->where('id', $instance->id)->get()->all(),
        Schema::hasTable('service_instance_runtime_secrets') ? DB::table('service_instance_runtime_secrets')->get()->all() : [],
    ]);
    $exposed = json_encode([
        $info->meta, $info->externalRef, $info->serverSettings, $info->providerSettings,
        $provisioner->panel(directAdminInfo($instance)),
    ]);

    expect($password)->not->toBe('')
        ->and($messages)->not->toBe('')
        ->and($info->serverSettings)->toBeNull()
        ->and($info->providerSettings)->toBeNull();
    foreach ([DIRECTADMIN_TEST_TOKEN, $password] as $secret) {
        expect($persisted)->not->toContain($secret)
            ->and($exposed)->not->toContain($secret)
            ->and($messages)->not->toContain($secret);
    }
});

test('directadmin health lists user packages through the documented read-only command', function () {
    $provisioner = enableDirectAdmin();
    fakeDirectAdmin([
        'GET /CMD_API_PACKAGES_USER' => [daList(['starter', 'pro']), daError(), Http::response('<html>login</html>', 200)],
    ]);

    $ok = $provisioner->testServer(directAdminServerSettings());
    $rejected = $provisioner->testServer(directAdminServerSettings());
    $malformed = $provisioner->testServer(directAdminServerSettings());
    $invalid = $provisioner->testServer(directAdminServerSettings(['api_url' => 'https://da.example.test:2222/path']));

    $requests = directAdminRequests('GET', '/CMD_API_PACKAGES_USER');
    expect($ok->ok)->toBeTrue()
        ->and($rejected->ok)->toBeFalse()
        ->and($malformed->ok)->toBeFalse()
        ->and($invalid->ok)->toBeFalse()
        ->and($requests)->toHaveCount(3)
        ->and($requests[0]->url())->toBe(DIRECTADMIN_TEST_URL.'/CMD_API_PACKAGES_USER')
        ->and(hasDirectAdminAuth($requests[0]))->toBeTrue();
});

test('directadmin refuses all provider http in the demo environment', function () {
    $provisioner = enableDirectAdmin();
    fakeDirectAdmin(['GET /CMD_API_PACKAGES_USER' => [daList(['starter'])]]);
    $environment = app()['env'];
    app()['env'] = 'demo';

    try {
        $result = $provisioner->testServer(directAdminServerSettings());
    } finally {
        app()['env'] = $environment;
    }

    expect($result->ok)->toBeFalse();
    Http::assertNothingSent();
});

test('directadmin customer panel shows the plain login url and username only', function () {
    $provisioner = enableDirectAdmin();
    $instance = directAdminInstance();
    directAdminClaim($instance, 'agopanl1');
    fakeDirectAdmin([]);

    $panel = $provisioner->panel(directAdminInfo($instance));
    $values = collect($panel?->fields)->pluck('value')->all();

    expect($panel)->not->toBeNull()
        ->and($values)->toContain('agopanl1')
        ->and($values)->toContain(DIRECTADMIN_TEST_URL)
        ->and($values)->toContain('example.com')
        ->and(json_encode($panel))->not->toContain(DIRECTADMIN_TEST_TOKEN)
        ->and($provisioner->actions(directAdminInfo($instance)))->toBe([]);
    Http::assertNothingSent();
});
