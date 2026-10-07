<?php

declare(strict_types=1);

use Agovena\Extensions\Convoy\ConvoyAccount;
use Agovena\Extensions\Convoy\ConvoyEndpoint;
use Agovena\Extensions\Convoy\ConvoyProvisioner;
use Agovena\Modules\Provisioning\EloquentProvisionedServiceResolver;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ServiceInstanceRuntimeSecretStore;
use Agovena\Modules\Provisioning\Support\ServerProviderException;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use App\Models\Customer;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

const CV_LC_TOKEN = 'cv-token-SECRET-4f1a2c';
const CV_LC_URL = 'https://convoy.example.test';
const CV_LC_ENDPOINT = 'https://convoy.example.test:443';
const CV_LC_UUID = '0f4c7f43-1a3c-4d2e-9b7a-000000000001';
const CV_LC_TEMPLATE = '7a3d3b8e-2c6f-4c1d-8e9f-1b2a3c4d5e6f';
const CV_LC_API = '/api/application';
const CV_LC_SERVER = CV_LC_API.'/servers/'.CV_LC_UUID;
const CV_LC_MIB = 1048576;
const CV_LC_GIB = 1073741824;

beforeEach(function (): void {
    installAndEnableModule('provisioning');
    app(ExtensionManager::class)->discover();
    installAndEnableExtension('convoy');
    app()->forgetInstance(ConvoyProvisioner::class);
    cvLcPointAt(CV_LC_URL);
    cvLcRegisterFake();
});

function cvLcPointAt(string $url): void
{
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('convoy', 'api_url', $url);
    $settings->set('convoy', 'api_token', CV_LC_TOKEN, secret: true);
    $settings->set('convoy', 'verify_tls', true);
    $settings->set('convoy', 'timeout', '20');
}

function cvLcProvisioner(): ConvoyProvisioner
{
    return app(ConvoyProvisioner::class);
}

function cvLcUuid(int $number): string
{
    return sprintf('0f4c7f43-1a3c-4d2e-9b7a-%012d', $number);
}

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function cvLcSettings(array $overrides = []): array
{
    return array_merge([
        'node_id' => '2',
        'template_uuid' => CV_LC_TEMPLATE,
        'cpu' => '2',
        'memory_mb' => '2048',
        'disk_mb' => '20480',
        'bandwidth_gb' => '',
        'snapshots' => '1',
        'backups' => '2',
    ], $overrides);
}

/** @param array<string, mixed>|null $settings */
function cvLcInstance(?Customer $customer = null, ?array $settings = null, array $meta = [], ?string $externalRef = null, ?string $email = 'default'): ServiceInstance
{
    static $sequence = 0;
    $sequence++;
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-CVLC-'.$sequence.'-'.bin2hex(random_bytes(3)),
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'convoy',
        'customer_id' => $customer?->id,
        'customer_email' => $email === 'default' ? ($customer?->email ?? 'buyer'.$sequence.'@example.test') : $email,
        'customer_name' => 'Buyer '.$sequence,
        'external_ref' => $externalRef,
        'meta' => array_merge(['label' => 'VPS'], $meta),
    ]);
    app(ServiceInstanceRuntimeSecretStore::class)->put($instance->id, null, $settings ?? cvLcSettings());

    return $instance->fresh();
}

function cvLcInfo(ServiceInstance $instance): ServiceInstanceInfo
{
    return EloquentProvisionedServiceResolver::info($instance->fresh() ?? $instance);
}

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function cvLcLimits(array $overrides = []): array
{
    return array_merge(['cpu' => 2, 'memory' => 2048 * CV_LC_MIB, 'disk' => 20480 * CV_LC_MIB, 'snapshots' => 1, 'backups' => 2, 'bandwidth' => null], $overrides);
}

/**
 * Convoy ServerBuildTransformer body for GET/POST/PATCH /servers.
 *
 * @param  array<string, mixed>  $overrides
 * @param  array<string, mixed>  $limits
 * @param  list<int>  $ipv4
 * @param  list<int>  $ipv6
 * @return array<string, mixed>
 */
function cvLcServer(array $overrides = [], array $limits = [], array $ipv4 = [11], array $ipv6 = [12]): array
{
    $address = static fn (string $type): Closure => static fn (int $id): array => [
        'id' => $id, 'address_pool_id' => 1, 'server_id' => 5, 'type' => $type,
        'address' => $type === 'ipv4' ? '203.0.113.'.$id : '2001:db8::'.$id, 'cidr' => $type === 'ipv4' ? 24 : 64,
        'gateway' => $type === 'ipv4' ? '203.0.113.1' : '2001:db8::1', 'mac_address' => null,
    ];

    return ['data' => array_merge([
        'id' => '0f4c7f43',
        'internal_id' => 5,
        'uuid' => CV_LC_UUID,
        'node_id' => 2,
        'user_id' => 21,
        'vmid' => 100,
        'hostname' => 'vps.example.test',
        'name' => 'VPS',
        'description' => null,
        'status' => null,
        'usages' => ['bandwidth' => 0],
        'limits' => cvLcLimits($limits) + [
            'addresses' => ['ipv4' => array_map($address('ipv4'), $ipv4), 'ipv6' => array_map($address('ipv6'), $ipv6)],
            'mac_address' => null,
        ],
    ], $overrides)];
}

/** @param array<string, mixed> $overrides */
function cvLcUser(int $id, array $overrides = []): Closure
{
    return static fn (Request $request): PromiseInterface => Http::response(['data' => array_merge([
        'id' => $id, 'name' => $request->data()['name'] ?? null, 'email' => $request->data()['email'] ?? null,
        'email_verified_at' => null, 'root_admin' => false, 'servers_count' => 0,
    ], $overrides)], 200);
}

function cvLcNoContent(): PromiseInterface
{
    return Http::response([], 204);
}

function cvLcState(): stdClass
{
    static $state = null;

    return $state ??= new stdClass;
}

function cvLcRegisterFake(): void
{
    cvLcFake([]);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $state = cvLcState();
        $parts = parse_url($request->url());
        parse_str((string) ($parts['query'] ?? ''), $query);
        $key = $request->method().' '.($parts['path'] ?? '');
        $state->log[] = [
            'key' => $key,
            'host' => ($parts['scheme'] ?? '').'://'.($parts['host'] ?? ''),
            'query' => $query,
            'body' => $request->method() === 'GET' ? [] : $request->data(),
            'authorization' => $request->header('Authorization')[0] ?? null,
        ];
        if (($state->script[$key] ?? []) === []) {
            return Http::response(['unexpected' => $key], 599);
        }
        $next = array_shift($state->script[$key]);
        if ($next instanceof PromiseInterface) {
            return $next;
        }

        return is_callable($next) ? $next($request) : Http::response($next, 200);
    });
}

/**
 * Replaces the Convoy script. Each "METHOD /path" maps to a queue of responses;
 * arrays are 200 JSON bodies. Unscripted calls answer 599.
 *
 * @param  array<string, list<mixed>>  $script
 */
function cvLcFake(array $script): ArrayObject
{
    $state = cvLcState();
    $state->script = $script;
    $state->log = new ArrayObject;

    return $state->log;
}

/** @return list<string> */
function cvLcKeys(ArrayObject $log): array
{
    return array_values(array_map(static fn (array $entry): string => $entry['key'], $log->getArrayCopy()));
}

/** @return array<string, list<mixed>> */
function cvLcCreateScript(string $uuid = CV_LC_UUID, int $userId = 21): array
{
    return [
        'POST '.CV_LC_API.'/users' => [cvLcUser($userId)],
        'POST '.CV_LC_API.'/servers' => [cvLcServer(['uuid' => $uuid, 'user_id' => $userId, 'status' => 'installing'])],
    ];
}

function cvLcClaim(ServiceInstance $instance): ?ConvoyAccount
{
    return ConvoyAccount::query()->where('service_instance_id', $instance->id)->first();
}

/** @return array{0: ServiceInstance, 1: ConvoyAccount} */
function cvLcActive(?Customer $customer = null): array
{
    $instance = cvLcInstance($customer);
    cvLcFake(cvLcCreateScript());
    cvLcProvisioner()->provision(cvLcInfo($instance));

    return [$instance, cvLcClaim($instance)];
}

function cvLcRefused(callable $operation): ValidationException
{
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('instance');

        return $exception;
    }

    throw new RuntimeException('Expected the Convoy operation to be refused.');
}

/** @return array<string, string> */
function cvLcBiggerPlan(): array
{
    return ['cpu' => '4', 'memory_mb' => '4096', 'disk_mb' => '40960', 'bandwidth_gb' => '1000', 'snapshots' => '2', 'backups' => '3'];
}

/** @return array<string, int> */
function cvLcBiggerLimits(): array
{
    return ['cpu' => 4, 'memory' => 4096 * CV_LC_MIB, 'disk' => 40960 * CV_LC_MIB, 'snapshots' => 2, 'backups' => 3, 'bandwidth' => 1000 * CV_LC_GIB];
}

test('convoy migration creates the claim table with unique identity columns and is reversible', function () {
    expect(Schema::hasColumns('convoy_accounts', [
        'id', 'service_instance_id', 'endpoint', 'remote_id', 'remote_name', 'user_id', 'plan', 'state', 'revision', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $row = ['endpoint' => CV_LC_ENDPOINT, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()];
    DB::table('convoy_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => CV_LC_UUID]);

    expect(fn () => DB::table('convoy_accounts')->insert($row + ['service_instance_id' => 900002, 'remote_id' => CV_LC_UUID]))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('convoy_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => cvLcUuid(2)]))
        ->toThrow(QueryException::class)
        ->and(DB::table('convoy_accounts')->where('service_instance_id', 900001)->value('revision'))->toBe(0);

    DB::table('convoy_accounts')->insert(['endpoint' => 'https://other.example.test:443'] + $row + ['service_instance_id' => 900004, 'remote_id' => CV_LC_UUID]);
    expect(DB::table('convoy_accounts')->count())->toBe(2);

    $migration = require config('agovena.packages.optional_packages_path').'/extensions/provisioning/convoy/database/migrations/2026_10_05_000000_create_convoy_accounts_table.php';
    $migration->up();
    $migration->down();
    expect(Schema::hasTable('convoy_accounts'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('convoy_accounts'))->toBeTrue();
});

test('convoy endpoint identity is normalized and rejects paths and credentials', function () {
    expect(ConvoyEndpoint::normalize('HTTPS://Convoy.Example.TEST/'))->toBe(CV_LC_ENDPOINT)
        ->and(ConvoyEndpoint::normalize('http://10.0.0.5:8080'))->toBe('http://10.0.0.5:8080');

    foreach (['https://convoy.example.test/api/application', 'https://user:pass@convoy.example.test', 'ftp://convoy.example.test', 'https://convoy.example.test?x=1', ''] as $url) {
        expect(fn () => ConvoyEndpoint::normalize($url))->toThrow(ServerProviderException::class);
    }
});

test('convoy create sends the documented calls once and a retry is idempotent', function () {
    $instance = cvLcInstance();
    $log = cvLcFake(cvLcCreateScript());

    cvLcProvisioner()->provision(cvLcInfo($instance));

    expect(cvLcKeys($log))->toBe(['POST '.CV_LC_API.'/users', 'POST '.CV_LC_API.'/servers'])
        ->and(array_values(array_unique(array_column($log->getArrayCopy(), 'authorization'))))->toBe(['Bearer '.CV_LC_TOKEN])
        ->and(array_values(array_unique(array_column($log->getArrayCopy(), 'host'))))->toBe([CV_LC_URL])
        ->and(Arr::except($log[0]['body'], ['password']))->toBe(['name' => $instance->customer_name, 'email' => $instance->customer_email, 'root_admin' => false])
        ->and(strlen($log[0]['body']['password']))->toBe(32)
        ->and(Arr::except($log[1]['body'], ['account_password']))->toBe([
            'name' => 'agovena-'.$instance->id,
            'user_id' => 21,
            'node_id' => 2,
            'vmid' => null,
            'hostname' => 'agovena-'.$instance->id,
            'limits' => cvLcLimits(),
            'should_create_server' => true,
            'template_uuid' => CV_LC_TEMPLATE,
            'start_on_completion' => true,
        ])
        ->and(strlen($log[1]['body']['account_password']))->toBe(32)
        ->and($log[1]['body']['account_password'])->not->toBe($log[0]['body']['password']);

    $claim = cvLcClaim($instance);
    expect($claim->only(['endpoint', 'remote_id', 'remote_name', 'user_id', 'plan', 'state']))->toBe([
        'endpoint' => CV_LC_ENDPOINT,
        'remote_id' => CV_LC_UUID,
        'remote_name' => 'vps.example.test',
        'user_id' => 21,
        'plan' => json_encode(cvLcLimits()),
        'state' => 'active',
    ]);

    $log = cvLcFake([]);
    cvLcProvisioner()->provision(cvLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and(cvLcClaim($instance)->revision)->toBe($claim->revision);
});

test('convoy orchestrated provisioning activates the service without persisting secrets', function () {
    $instance = cvLcInstance();
    $log = cvLcFake(cvLcCreateScript() + ['GET '.CV_LC_SERVER => [cvLcServer()]]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->status)->toBe(ServiceInstanceStatus::Active)
        ->and($result->external_ref)->toBe(CV_LC_UUID)
        ->and(cvLcKeys($log))->toContain('GET '.CV_LC_SERVER);
    $persisted = json_encode([$result->fresh()->getAttributes(), cvLcClaim($instance)->getAttributes()]);
    expect($persisted)->not->toContain(CV_LC_TOKEN)
        ->and($persisted)->not->toContain($log[0]['body']['password'])
        ->and($persisted)->not->toContain($log[1]['body']['account_password']);
});

test('convoy reuses only a user that Agovena created for the same customer on the same endpoint', function () {
    $customer = Customer::factory()->create();
    cvLcActive($customer);
    $second = cvLcInstance($customer);
    $script = cvLcCreateScript(cvLcUuid(2));
    unset($script['POST '.CV_LC_API.'/users']);
    $log = cvLcFake($script);

    cvLcProvisioner()->provision(cvLcInfo($second));

    expect(cvLcKeys($log))->toBe(['POST '.CV_LC_API.'/servers'])
        ->and($log[0]['body']['user_id'])->toBe(21)
        ->and(cvLcClaim($second)->only(['user_id', 'remote_id', 'state']))->toBe(['user_id' => 21, 'remote_id' => cvLcUuid(2), 'state' => 'active']);

    $stranger = cvLcInstance(Customer::factory()->create());
    $log = cvLcFake(cvLcCreateScript(cvLcUuid(3), 22));
    cvLcProvisioner()->provision(cvLcInfo($stranger));

    expect(cvLcKeys($log))->toContain('POST '.CV_LC_API.'/users')
        ->and(cvLcClaim($stranger)->user_id)->toBe(22);

    $elsewhere = Customer::factory()->create();
    ConvoyAccount::query()->create([
        'service_instance_id' => cvLcInstance($elsewhere)->id,
        'endpoint' => 'https://other.example.test:443',
        'remote_id' => cvLcUuid(9),
        'user_id' => 30,
        'state' => 'active',
    ]);
    $moved = cvLcInstance($elsewhere);
    $log = cvLcFake(cvLcCreateScript(cvLcUuid(4), 23));
    cvLcProvisioner()->provision(cvLcInfo($moved));

    expect(cvLcKeys($log))->toContain('POST '.CV_LC_API.'/users')
        ->and(cvLcClaim($moved)->user_id)->toBe(23);
});

test('convoy refuses a server uuid that another service already claims', function () {
    [$first, $firstClaim] = cvLcActive();
    $second = cvLcInstance();
    cvLcFake(cvLcCreateScript(CV_LC_UUID, 22));

    $exception = cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($second)));

    expect($exception->errors()['instance'][0])->toBe(__('convoy::messages.errors.claimed'))
        ->and(cvLcClaim($second)->state)->toBe('creating')
        ->and(cvLcClaim($second)->remote_id)->toBe('pending:'.$second->id)
        ->and(cvLcClaim($first)->revision)->toBe($firstClaim->revision);

    $log = cvLcFake([]);
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($second)));
    expect($log->count())->toBe(0)
        ->and(cvLcClaim($second)->state)->toBe('unknown');
});

test('convoy server create timeout is never blindly retried and goes to manual review', function () {
    $instance = cvLcInstance();
    $script = cvLcCreateScript();
    $script['POST '.CV_LC_API.'/servers'] = [fn () => Http::failedConnection()];
    cvLcFake($script);

    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($instance)));

    expect(cvLcClaim($instance)->only(['state', 'user_id', 'remote_id']))->toBe(['state' => 'unknown', 'user_id' => 21, 'remote_id' => 'pending:'.$instance->id]);

    $log = cvLcFake(cvLcCreateScript());
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($instance)));
    expect($log->count())->toBe(0);

    $other = cvLcInstance();
    $script = cvLcCreateScript(cvLcUuid(2));
    $script['POST '.CV_LC_API.'/servers'] = [fn () => Http::failedConnection()];
    cvLcFake($script);
    $result = app(ProvisioningOrchestrator::class)->provision($other);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and(cvLcClaim($other)->state)->toBe('unknown');
});

test('convoy user create timeout never looks the user up by email', function () {
    $instance = cvLcInstance();
    $script = cvLcCreateScript();
    $script['POST '.CV_LC_API.'/users'] = [fn () => Http::failedConnection()];
    $log = cvLcFake($script);

    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($instance)));

    expect(cvLcKeys($log))->toBe(['POST '.CV_LC_API.'/users'])
        ->and(cvLcClaim($instance)->only(['state', 'user_id']))->toBe(['state' => 'unknown', 'user_id' => null]);

    $log = cvLcFake(cvLcCreateScript());
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($instance)));
    expect($log->count())->toBe(0);
});

test('convoy provider failure bodies with http 200 are never marked active', function () {
    $instance = cvLcInstance();
    $script = cvLcCreateScript();
    $script['POST '.CV_LC_API.'/servers'] = [['message' => 'Server creation failed']];
    cvLcFake($script);
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($instance)));
    expect(cvLcClaim($instance)->state)->toBe('unknown');

    $foreign = cvLcInstance();
    $script = cvLcCreateScript(cvLcUuid(2));
    $script['POST '.CV_LC_API.'/servers'] = [cvLcServer(['uuid' => cvLcUuid(2), 'user_id' => 99])];
    cvLcFake($script);
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($foreign)));
    expect(cvLcClaim($foreign)->only(['state', 'remote_id']))->toBe(['state' => 'unknown', 'remote_id' => 'pending:'.$foreign->id]);

    $admin = cvLcInstance();
    $script = cvLcCreateScript(cvLcUuid(3));
    $script['POST '.CV_LC_API.'/users'] = [cvLcUser(21, ['root_admin' => true])];
    $log = cvLcFake($script);
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($admin)));
    expect(cvLcKeys($log))->toBe(['POST '.CV_LC_API.'/users'])
        ->and(cvLcClaim($admin)->only(['state', 'user_id']))->toBe(['state' => 'unknown', 'user_id' => null]);

    [$active] = cvLcActive();
    cvLcFake(['POST '.CV_LC_SERVER.'/settings/suspend' => [['errors' => ['failed']]]]);
    cvLcRefused(fn () => cvLcProvisioner()->suspend(cvLcInfo($active)));
    expect(cvLcClaim($active)->state)->toBe('active');
});

test('convoy definitive rejections fail the claim and a retry keeps the recorded user', function () {
    $instance = cvLcInstance();
    $script = cvLcCreateScript();
    $script['POST '.CV_LC_API.'/servers'] = [Http::response(['message' => 'The node does not have enough memory.'], 422)];
    cvLcFake($script);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::Failed)
        ->and(cvLcClaim($instance)->only(['state', 'user_id']))->toBe(['state' => 'failed', 'user_id' => 21]);

    $script = cvLcCreateScript();
    unset($script['POST '.CV_LC_API.'/users']);
    $log = cvLcFake($script);
    cvLcProvisioner()->provision(cvLcInfo($instance));

    expect(cvLcKeys($log))->toBe(['POST '.CV_LC_API.'/servers'])
        ->and(cvLcClaim($instance)->state)->toBe('active');

    $taken = cvLcInstance();
    $script = cvLcCreateScript(cvLcUuid(2));
    $script['POST '.CV_LC_API.'/users'] = [Http::response(['message' => 'The email has already been taken.'], 422)];
    $log = cvLcFake($script);
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($taken)));

    expect(cvLcKeys($log))->toBe(['POST '.CV_LC_API.'/users'])
        ->and(cvLcClaim($taken)->only(['state', 'user_id']))->toBe(['state' => 'failed', 'user_id' => null]);
});

test('convoy refuses invalid product settings or a missing customer email before claiming', function (array $settings, ?string $email) {
    $instance = cvLcInstance(settings: $settings, email: $email);
    $log = cvLcFake(cvLcCreateScript());

    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($instance)));

    expect($log->count())->toBe(0)
        ->and(cvLcClaim($instance))->toBeNull();
})->with([
    'missing node' => [Arr::except(cvLcSettings(), ['node_id']), 'default'],
    'template is not a uuid' => [cvLcSettings(['template_uuid' => '12']), 'default'],
    'zero cpu' => [cvLcSettings(['cpu' => '0']), 'default'],
    'memory below 16 MiB' => [cvLcSettings(['memory_mb' => '8']), 'default'],
    'negative backups' => [cvLcSettings(['backups' => '-1']), 'default'],
    'non numeric bandwidth' => [cvLcSettings(['bandwidth_gb' => 'unlimited']), 'default'],
    'no email' => [cvLcSettings(), ''],
]);

test('convoy refuses every provider call in the demo environment', function () {
    [$instance] = cvLcActive();
    $fresh = cvLcInstance();
    app()['env'] = 'demo';
    $log = cvLcFake([]);

    try {
        cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($fresh)));
        cvLcRefused(fn () => cvLcProvisioner()->suspend(cvLcInfo($instance)));
        cvLcRefused(fn () => cvLcProvisioner()->syncStatus(cvLcInfo($instance)));
        $health = cvLcProvisioner()->testServer(['api_url' => CV_LC_URL, 'api_token' => CV_LC_TOKEN]);
    } finally {
        app()['env'] = 'testing';
    }

    expect($log->count())->toBe(0)
        ->and($health->ok)->toBeFalse()
        ->and(cvLcClaim($fresh)->state)->toBe('failed')
        ->and(cvLcClaim($instance)->state)->toBe('active');
});

test('convoy suspend and unsuspend call the documented endpoints and track state', function () {
    [$instance] = cvLcActive();
    $log = cvLcFake([
        'POST '.CV_LC_SERVER.'/settings/suspend' => [cvLcNoContent()],
        'POST '.CV_LC_SERVER.'/settings/unsuspend' => [cvLcNoContent()],
    ]);

    cvLcProvisioner()->suspend(cvLcInfo($instance));
    expect(cvLcClaim($instance)->state)->toBe('suspended');
    cvLcProvisioner()->suspend(cvLcInfo($instance));
    cvLcProvisioner()->unsuspend(cvLcInfo($instance));

    expect(cvLcKeys($log))->toBe(['POST '.CV_LC_SERVER.'/settings/suspend', 'POST '.CV_LC_SERVER.'/settings/unsuspend'])
        ->and(cvLcClaim($instance)->state)->toBe('active');
});

test('convoy suspend and unsuspend failures keep the stored state', function () {
    [$instance] = cvLcActive();
    cvLcFake(['POST '.CV_LC_SERVER.'/settings/suspend' => [Http::response(['message' => 'Server error'], 500)]]);
    cvLcRefused(fn () => cvLcProvisioner()->suspend(cvLcInfo($instance)));
    expect(cvLcClaim($instance)->state)->toBe('active');

    ConvoyAccount::query()->whereKey(cvLcClaim($instance)->id)->update(['state' => 'suspended']);
    cvLcFake(['POST '.CV_LC_SERVER.'/settings/unsuspend' => [fn () => Http::failedConnection()]]);
    cvLcRefused(fn () => cvLcProvisioner()->unsuspend(cvLcInfo($instance)));
    expect(cvLcClaim($instance)->state)->toBe('suspended');
});

test('convoy terminate deletes only the owned server and keeps the identifier claimed', function () {
    [$instance] = cvLcActive();
    cvLcFake(['DELETE '.CV_LC_SERVER => [Http::response(['message' => 'Server error'], 500)]]);
    cvLcRefused(fn () => cvLcProvisioner()->terminate(cvLcInfo($instance)));
    expect(cvLcClaim($instance)->state)->toBe('active');

    $log = cvLcFake(['DELETE '.CV_LC_SERVER => [cvLcNoContent()]]);
    cvLcProvisioner()->terminate(cvLcInfo($instance));
    cvLcProvisioner()->terminate(cvLcInfo($instance));

    expect(cvLcKeys($log))->toBe(['DELETE '.CV_LC_SERVER])
        ->and($log[0]['body'])->toBe([])
        ->and(cvLcClaim($instance)->only(['state', 'remote_id']))->toBe(['state' => 'terminated', 'remote_id' => CV_LC_UUID])
        ->and(cvLcProvisioner()->syncStatus(cvLcInfo($instance))->status)->toBe('terminated')
        ->and($log->count())->toBe(1);

    $gone = cvLcInstance();
    cvLcFake(cvLcCreateScript(cvLcUuid(8)));
    cvLcProvisioner()->provision(cvLcInfo($gone));
    cvLcFake(['DELETE '.CV_LC_API.'/servers/'.cvLcUuid(8) => [Http::response(['message' => 'Not found'], 404)]]);
    cvLcProvisioner()->terminate(cvLcInfo($gone));
    expect(cvLcClaim($gone)->state)->toBe('terminated');
});

test('convoy terminate never touches unverified claims and closes rejected or unclaimed services without network', function () {
    $unknown = cvLcInstance();
    $script = cvLcCreateScript();
    $script['POST '.CV_LC_API.'/servers'] = [fn () => Http::failedConnection()];
    cvLcFake($script);
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($unknown)));

    $rejected = cvLcInstance();
    $script = cvLcCreateScript(cvLcUuid(2));
    $script['POST '.CV_LC_API.'/servers'] = [Http::response(['message' => 'Invalid'], 422)];
    cvLcFake($script);
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($rejected)));

    $log = cvLcFake([]);
    cvLcRefused(fn () => cvLcProvisioner()->terminate(cvLcInfo($unknown)));
    cvLcProvisioner()->terminate(cvLcInfo($rejected));
    cvLcProvisioner()->terminate(cvLcInfo(cvLcInstance()));

    expect($log->count())->toBe(0)
        ->and(cvLcClaim($unknown)->state)->toBe('unknown')
        ->and(cvLcClaim($rejected)->state)->toBe('terminated');
});

test('convoy plan change sends the new limits with the documented build call and verifies them', function () {
    [$instance] = cvLcActive();
    $log = cvLcFake([
        'GET '.CV_LC_SERVER => [cvLcServer(), cvLcServer(limits: cvLcBiggerLimits())],
        'PATCH '.CV_LC_SERVER.'/settings/build' => [cvLcServer(limits: cvLcBiggerLimits())],
    ]);

    cvLcProvisioner()->changePlan(cvLcInfo($instance), ['provider_settings' => cvLcBiggerPlan()]);
    cvLcProvisioner()->changePlan(cvLcInfo($instance), ['provider_settings' => cvLcBiggerPlan()]);

    expect(cvLcKeys($log))->toBe(['GET '.CV_LC_SERVER, 'PATCH '.CV_LC_SERVER.'/settings/build', 'GET '.CV_LC_SERVER])
        ->and($log[1]['body'])->toBe([
            'cpu' => 4,
            'memory' => 4096 * CV_LC_MIB,
            'disk' => 40960 * CV_LC_MIB,
            'snapshot_limit' => 2,
            'backup_limit' => 3,
            'bandwidth_limit' => 1000 * CV_LC_GIB,
            'address_ids' => [11, 12],
        ])
        ->and(cvLcClaim($instance)->plan)->toBe(json_encode(cvLcBiggerLimits()))
        ->and(cvLcClaim($instance)->state)->toBe('active');
});

test('convoy plan change re-sends the address ids the server currently holds', function () {
    [$instance] = cvLcActive();
    $current = static fn (array $limits = []): array => cvLcServer(limits: $limits, ipv4: [31, 32], ipv6: [40]);
    $log = cvLcFake([
        'GET '.CV_LC_SERVER => [$current(), $current(cvLcBiggerLimits())],
        'PATCH '.CV_LC_SERVER.'/settings/build' => [$current(cvLcBiggerLimits())],
    ]);

    cvLcProvisioner()->changePlan(cvLcInfo($instance), ['provider_settings' => cvLcBiggerPlan()]);

    expect($log[1]['body']['address_ids'])->toBe([31, 32, 40]);
});

test('convoy plan change keeps the stored plan when the change cannot be verified', function (array $script, array $keys) {
    [$instance] = cvLcActive();
    $log = cvLcFake($script);

    cvLcRefused(fn () => cvLcProvisioner()->changePlan(cvLcInfo($instance), ['provider_settings' => cvLcBiggerPlan()]));

    expect(cvLcKeys($log))->toBe($keys)
        ->and(cvLcClaim($instance)->plan)->toBe(json_encode(cvLcLimits()))
        ->and(cvLcClaim($instance)->state)->toBe('active');
})->with([
    'limits unchanged after the call' => [
        ['GET '.CV_LC_SERVER => [cvLcServer(), cvLcServer()], 'PATCH '.CV_LC_SERVER.'/settings/build' => [cvLcServer()]],
        ['GET '.CV_LC_SERVER, 'PATCH '.CV_LC_SERVER.'/settings/build', 'GET '.CV_LC_SERVER],
    ],
    'addresses detached after the call' => [
        ['GET '.CV_LC_SERVER => [cvLcServer(), cvLcServer(limits: cvLcBiggerLimits(), ipv4: [], ipv6: [])], 'PATCH '.CV_LC_SERVER.'/settings/build' => [cvLcServer(limits: cvLcBiggerLimits())]],
        ['GET '.CV_LC_SERVER, 'PATCH '.CV_LC_SERVER.'/settings/build', 'GET '.CV_LC_SERVER],
    ],
    'build call rejected' => [
        fn () => ['GET '.CV_LC_SERVER => [cvLcServer()], 'PATCH '.CV_LC_SERVER.'/settings/build' => [Http::response(['message' => 'The node does not have enough disk.'], 422)]],
        ['GET '.CV_LC_SERVER, 'PATCH '.CV_LC_SERVER.'/settings/build'],
    ],
    'build call answers an error body with 200' => [
        ['GET '.CV_LC_SERVER => [cvLcServer()], 'PATCH '.CV_LC_SERVER.'/settings/build' => [['message' => 'failed']]],
        ['GET '.CV_LC_SERVER, 'PATCH '.CV_LC_SERVER.'/settings/build'],
    ],
    'server owned by another user' => [
        ['GET '.CV_LC_SERVER => [cvLcServer(['user_id' => 99])]],
        ['GET '.CV_LC_SERVER],
    ],
]);

test('convoy plan change refuses invalid limits and unverified or terminated claims without network', function () {
    [$instance] = cvLcActive();
    $log = cvLcFake([]);

    foreach ([[], ['provider_settings' => []], ['provider_settings' => ['cpu' => '0'] + cvLcBiggerPlan()], 'plan-name'] as $plan) {
        cvLcRefused(fn () => cvLcProvisioner()->changePlan(cvLcInfo($instance), $plan));
    }
    ConvoyAccount::query()->whereKey(cvLcClaim($instance)->id)->update(['state' => 'unknown']);
    cvLcRefused(fn () => cvLcProvisioner()->changePlan(cvLcInfo($instance), ['provider_settings' => cvLcBiggerPlan()]));
    ConvoyAccount::query()->whereKey(cvLcClaim($instance)->id)->update(['state' => 'terminated']);
    cvLcRefused(fn () => cvLcProvisioner()->changePlan(cvLcInfo($instance), ['provider_settings' => cvLcBiggerPlan()]));

    expect($log->count())->toBe(0)
        ->and(cvLcClaim($instance)->plan)->toBe(json_encode(cvLcLimits()));
});

test('convoy sync maps the documented server status and fails closed otherwise', function () {
    [$instance] = cvLcActive();
    $log = cvLcFake(['GET '.CV_LC_SERVER => [
        cvLcServer(),
        cvLcServer(['status' => 'suspended']),
        cvLcServer(),
        cvLcServer(['status' => 'installing']),
        cvLcServer(['status' => 'install_failed']),
        cvLcServer(['status' => 'deleting']),
        cvLcServer(['status' => 'deletion_failed']),
        cvLcServer(['status' => 'running']),
        cvLcServer(['user_id' => 99]),
        cvLcServer(['uuid' => cvLcUuid(2)]),
        Http::response(['message' => 'Not found'], 404),
    ]]);

    $active = cvLcProvisioner()->syncStatus(cvLcInfo($instance));
    expect($active->status)->toBe('active')
        ->and($active->externalRef)->toBe(CV_LC_UUID)
        ->and($active->meta['provider_mapping'])->toBe(['provider_id' => CV_LC_UUID, 'user_id' => 21]);

    expect(cvLcProvisioner()->syncStatus(cvLcInfo($instance))->status)->toBe('suspended')
        ->and(cvLcClaim($instance)->state)->toBe('suspended')
        ->and(cvLcProvisioner()->syncStatus(cvLcInfo($instance))->status)->toBe('active')
        ->and(cvLcClaim($instance)->state)->toBe('active')
        ->and(cvLcProvisioner()->syncStatus(cvLcInfo($instance))->status)->toBe('provisioning')
        ->and(cvLcClaim($instance)->state)->toBe('active');

    foreach (range(1, 7) as $ignored) {
        cvLcRefused(fn () => cvLcProvisioner()->syncStatus(cvLcInfo($instance)));
    }

    expect($log->count())->toBe(11)
        ->and($log[0]['query'])->toBe([])
        ->and(cvLcClaim($instance)->state)->toBe('active');
});

test('convoy sync of an unverified claim refuses without network and reports absence otherwise', function () {
    $unknown = cvLcInstance();
    $script = cvLcCreateScript();
    $script['POST '.CV_LC_API.'/servers'] = [fn () => Http::failedConnection()];
    cvLcFake($script);
    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($unknown)));

    $log = cvLcFake([]);
    cvLcRefused(fn () => cvLcProvisioner()->syncStatus(cvLcInfo($unknown)));
    $absent = cvLcProvisioner()->syncStatus(cvLcInfo(cvLcInstance()));

    expect($log->count())->toBe(0)
        ->and($absent->meta['provider_reconciliation'])->toBe('absent');
});

test('convoy lifecycle is refused without network when the endpoint is re-pointed', function () {
    [$instance] = cvLcActive();
    cvLcPointAt('https://other.example.test');
    $log = cvLcFake([]);

    $operations = [
        fn () => cvLcProvisioner()->provision(cvLcInfo($instance)),
        fn () => cvLcProvisioner()->suspend(cvLcInfo($instance)),
        fn () => cvLcProvisioner()->unsuspend(cvLcInfo($instance)),
        fn () => cvLcProvisioner()->terminate(cvLcInfo($instance)),
        fn () => cvLcProvisioner()->syncStatus(cvLcInfo($instance)),
        fn () => cvLcProvisioner()->changePlan(cvLcInfo($instance), ['provider_settings' => cvLcBiggerPlan()]),
    ];
    foreach ($operations as $operation) {
        expect(cvLcRefused($operation)->errors()['instance'][0])->toBe(__('convoy::messages.errors.endpoint_changed'));
    }

    expect($log->count())->toBe(0)
        ->and(cvLcClaim($instance)->state)->toBe('active');
});

test('convoy hand written provider mapping without a claim row is refused without network', function () {
    $instance = cvLcInstance(meta: ['provider_mapping' => ['provider_id' => CV_LC_UUID]], externalRef: CV_LC_UUID);
    $log = cvLcFake(cvLcCreateScript());

    foreach (['provision', 'suspend', 'unsuspend', 'terminate', 'syncStatus'] as $operation) {
        cvLcRefused(fn () => cvLcProvisioner()->{$operation}(cvLcInfo($instance)));
    }
    cvLcRefused(fn () => cvLcProvisioner()->changePlan(cvLcInfo($instance), ['provider_settings' => cvLcBiggerPlan()]));

    expect($log->count())->toBe(0)
        ->and(cvLcClaim($instance))->toBeNull();
});

test('convoy stale revision between read and write never overwrites the row', function () {
    [$instance, $claim] = cvLcActive();
    cvLcFake(['POST '.CV_LC_SERVER.'/settings/suspend' => [function () use ($claim) {
        DB::table('convoy_accounts')->where('id', $claim->id)->update(['revision' => 9]);

        return Http::response([], 204);
    }]]);

    cvLcRefused(fn () => cvLcProvisioner()->suspend(cvLcInfo($instance)));
    expect(cvLcClaim($instance)->only(['state', 'revision']))->toBe(['state' => 'active', 'revision' => 9]);

    $fresh = cvLcInstance();
    $script = cvLcCreateScript(cvLcUuid(2));
    $script['POST '.CV_LC_API.'/servers'] = [function () use ($fresh) {
        DB::table('convoy_accounts')->where('service_instance_id', $fresh->id)->update(['state' => 'failed', 'revision' => 5]);

        return Http::response(cvLcServer(['uuid' => cvLcUuid(2)]), 200);
    }];
    cvLcFake($script);

    cvLcRefused(fn () => cvLcProvisioner()->provision(cvLcInfo($fresh)));
    expect(cvLcClaim($fresh)->only(['state', 'revision', 'remote_id']))->toBe(['state' => 'failed', 'revision' => 5, 'remote_id' => 'pending:'.$fresh->id]);
});

test('convoy never leaks the api token or generated passwords', function () {
    $instance = cvLcInstance();
    $created = cvLcFake(cvLcCreateScript());
    cvLcProvisioner()->provision(cvLcInfo($instance));
    $secrets = [CV_LC_TOKEN, $created[0]['body']['password'], $created[1]['body']['account_password']];

    cvLcFake(['GET '.CV_LC_SERVER => [cvLcServer(), Http::response(['message' => CV_LC_TOKEN], 500)]]);
    $info = cvLcProvisioner()->syncStatus(cvLcInfo($instance));
    $exception = cvLcRefused(fn () => cvLcProvisioner()->syncStatus(cvLcInfo($instance)));
    $panel = cvLcProvisioner()->panel(cvLcInfo($instance));
    $output = json_encode([
        $info->meta, $info->serverSettings, $info->providerSettings, $info->externalRef, $exception->errors(), $exception->getMessage(),
        $panel, ConvoyAccount::query()->get()->toArray(), $instance->fresh()->getAttributes(),
    ]);

    foreach ($secrets as $secret) {
        expect($output)->not->toContain($secret);
    }
});

test('convoy panel shows the claimed server without network or power actions', function () {
    [$instance] = cvLcActive();
    $log = cvLcFake([]);

    $panel = cvLcProvisioner()->panel(cvLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and(collect($panel?->fields)->pluck('value')->all())->toContain(CV_LC_UUID, 'vps.example.test')
        ->and(cvLcProvisioner()->actions(cvLcInfo($instance)))->toBe([])
        ->and(cvLcProvisioner()->panel(cvLcInfo(cvLcInstance())))->toBeNull();
});

test('convoy health uses a read-only server listing and fails closed', function () {
    $settings = ['api_url' => CV_LC_URL, 'api_token' => CV_LC_TOKEN];
    $log = cvLcFake(['GET '.CV_LC_API.'/servers' => [['data' => [], 'meta' => ['pagination' => ['total' => 0]]], ['message' => 'nope'], Http::response('', 401)]]);

    expect(cvLcProvisioner()->testServer($settings)->ok)->toBeTrue()
        ->and(cvLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(cvLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(cvLcProvisioner()->testServer(['api_url' => CV_LC_URL])->ok)->toBeFalse()
        ->and(cvLcKeys($log))->toBe(['GET '.CV_LC_API.'/servers', 'GET '.CV_LC_API.'/servers', 'GET '.CV_LC_API.'/servers'])
        ->and($log[0]['query'])->toBe(['per_page' => '1'])
        ->and($log[0]['authorization'])->toBe('Bearer '.CV_LC_TOKEN);
});
