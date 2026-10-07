<?php

declare(strict_types=1);

use Agovena\Extensions\Virtfusion\VirtfusionAccount;
use Agovena\Extensions\Virtfusion\VirtfusionEndpoint;
use Agovena\Extensions\Virtfusion\VirtfusionProvisioner;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const VF_LC_TOKEN = 'vf-token-SECRET-7d3e9b';
const VF_LC_PASSWORD = 'vf-user-password-SECRET-51ac';
const VF_LC_URL = 'https://vf.example.test';
const VF_LC_ENDPOINT = 'https://vf.example.test:443';

beforeEach(function (): void {
    installAndEnableModule('provisioning');
    app(ExtensionManager::class)->discover();
    installAndEnableExtension('virtfusion');
    app()->forgetInstance(VirtfusionProvisioner::class);
    vfLcPointAt(VF_LC_URL);
    vfLcRegisterFake();
    Str::createRandomStringsUsing(static fn (int $length): string => str_repeat('K', $length));
});

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

function vfLcPointAt(string $url): void
{
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('virtfusion', 'api_url', $url);
    $settings->set('virtfusion', 'api_token', VF_LC_TOKEN, secret: true);
    $settings->set('virtfusion', 'verify_tls', true);
    $settings->set('virtfusion', 'timeout', '20');
}

function vfLcProvisioner(): VirtfusionProvisioner
{
    return app(VirtfusionProvisioner::class);
}

/** @param array<string, mixed> $settings */
function vfLcInstance(?Customer $customer = null, array $settings = ['package_id' => '3', 'hypervisor_group_id' => '2', 'template_id' => '7'], array $meta = [], ?string $externalRef = null, ?string $email = 'default'): ServiceInstance
{
    static $sequence = 0;
    $sequence++;
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-VFLC-'.$sequence.'-'.bin2hex(random_bytes(3)),
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'virtfusion',
        'customer_id' => $customer?->id,
        'customer_email' => $email === 'default' ? ($customer?->email ?? 'buyer'.$sequence.'@example.test') : $email,
        'customer_name' => 'Buyer '.$sequence,
        'external_ref' => $externalRef,
        'meta' => array_merge(['label' => 'VPS'], $meta),
    ]);
    app(ServiceInstanceRuntimeSecretStore::class)->put($instance->id, null, $settings);

    return $instance->fresh();
}

function vfLcInfo(ServiceInstance $instance): ServiceInstanceInfo
{
    return EloquentProvisionedServiceResolver::info($instance->fresh() ?? $instance);
}

function vfLcRelation(ServiceInstance $instance): string
{
    return 'agovena-'.base_convert((string) $instance->id, 10, 36).'-'.str_repeat('k', 16);
}

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function vfLcServer(array $overrides = []): array
{
    return array_merge([
        'id' => 70,
        'ownerId' => 21,
        'hypervisorId' => 2,
        'name' => 'Elliptical Way',
        'hostname' => null,
        'commissionStatus' => 3,
        'uuid' => 'b9fd9092-7200-4a24-96d4-76aedd664274',
        'state' => 'complete',
        'suspended' => false,
        'buildFailed' => false,
        'built' => '2026-10-05T10:00:00.000000Z',
    ], $overrides);
}

/** @return array<string, mixed> */
function vfLcPackages(int ...$enabled): array
{
    $packages = array_map(static fn (int $id): array => ['id' => $id, 'name' => 'Package '.$id, 'enabled' => true], $enabled);
    $packages[] = ['id' => 99, 'name' => 'Retired', 'enabled' => false];

    return ['data' => $packages];
}

function vfLcUser(int $id): PromiseInterface
{
    return Http::response(['data' => ['id' => $id, 'admin' => false, 'extRelationId' => 0, 'name' => 'Buyer', 'email' => 'buyer@example.test', 'suspended' => false, 'password' => VF_LC_PASSWORD]], 201);
}

function vfLcNoContent(): PromiseInterface
{
    return Http::response('', 204);
}

function vfLcState(): stdClass
{
    static $state = null;

    return $state ??= new stdClass;
}

function vfLcRegisterFake(): void
{
    vfLcFake([]);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $state = vfLcState();
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
 * Replaces the VirtFusion script. Each "METHOD /path" maps to a queue of responses;
 * arrays are 200 JSON bodies. Unscripted calls answer 599.
 *
 * @param  array<string, list<mixed>>  $script
 */
function vfLcFake(array $script): ArrayObject
{
    $state = vfLcState();
    $state->script = $script;
    $state->log = new ArrayObject;

    return $state->log;
}

/** @return list<string> */
function vfLcKeys(ArrayObject $log): array
{
    return array_values(array_map(static fn (array $entry): string => $entry['key'], $log->getArrayCopy()));
}

/** @return array<string, list<mixed>> */
function vfLcCreateScript(int $serverId = 70, int $userId = 21): array
{
    $server = vfLcServer(['id' => $serverId, 'ownerId' => $userId, 'built' => null, 'commissionStatus' => 0]);

    return [
        'GET /api/v1/packages' => [vfLcPackages(3, 4)],
        'POST /api/v1/users' => [vfLcUser($userId)],
        'POST /api/v1/servers' => [Http::response(['data' => $server], 201)],
        'POST /api/v1/servers/'.$serverId.'/build' => [['data' => $server]],
    ];
}

function vfLcClaim(ServiceInstance $instance): ?VirtfusionAccount
{
    return VirtfusionAccount::query()->where('service_instance_id', $instance->id)->first();
}

/** @return array{0: ServiceInstance, 1: VirtfusionAccount} */
function vfLcActive(?Customer $customer = null): array
{
    $instance = vfLcInstance($customer);
    vfLcFake(vfLcCreateScript());
    vfLcProvisioner()->provision(vfLcInfo($instance));

    return [$instance, vfLcClaim($instance)];
}

function vfLcRefused(callable $operation): ValidationException
{
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('instance');

        return $exception;
    }

    throw new RuntimeException('Expected the VirtFusion operation to be refused.');
}

test('virtfusion migration creates the claim table with unique identity columns and is reversible', function () {
    expect(Schema::hasColumns('virtfusion_accounts', [
        'id', 'service_instance_id', 'endpoint', 'remote_id', 'remote_name', 'user_id', 'user_relation', 'plan', 'state', 'revision', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $row = ['endpoint' => VF_LC_ENDPOINT, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()];
    DB::table('virtfusion_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => '70', 'user_relation' => 'agovena-a-x']);

    expect(fn () => DB::table('virtfusion_accounts')->insert($row + ['service_instance_id' => 900002, 'remote_id' => '70']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('virtfusion_accounts')->insert($row + ['service_instance_id' => 900003, 'remote_id' => '71', 'user_relation' => 'agovena-a-x']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('virtfusion_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => '72']))
        ->toThrow(QueryException::class)
        ->and(DB::table('virtfusion_accounts')->where('service_instance_id', 900001)->value('revision'))->toBe(0);

    DB::table('virtfusion_accounts')->insert(['endpoint' => 'https://other.example.test:443'] + $row + ['service_instance_id' => 900004, 'remote_id' => '70']);
    expect(DB::table('virtfusion_accounts')->count())->toBe(2);

    $migration = require config('agovena.packages.optional_packages_path').'/extensions/provisioning/virtfusion/database/migrations/2026_10_05_000000_create_virtfusion_accounts_table.php';
    $migration->up();
    $migration->down();
    expect(Schema::hasTable('virtfusion_accounts'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('virtfusion_accounts'))->toBeTrue();
});

test('virtfusion endpoint identity is normalized and rejects paths and credentials', function () {
    expect(VirtfusionEndpoint::normalize('HTTPS://VF.Example.TEST/'))->toBe(VF_LC_ENDPOINT)
        ->and(VirtfusionEndpoint::normalize('http://10.0.0.5:8080'))->toBe('http://10.0.0.5:8080');

    foreach (['https://vf.example.test/api/v1', 'https://user:pass@vf.example.test', 'ftp://vf.example.test', 'https://vf.example.test?x=1', ''] as $url) {
        expect(fn () => VirtfusionEndpoint::normalize($url))->toThrow(ServerProviderException::class);
    }
});

test('virtfusion create sends the documented calls once and a retry is idempotent', function () {
    $instance = vfLcInstance();
    $log = vfLcFake(vfLcCreateScript());

    vfLcProvisioner()->provision(vfLcInfo($instance));

    expect(vfLcKeys($log))->toBe(['GET /api/v1/packages', 'POST /api/v1/users', 'POST /api/v1/servers', 'POST /api/v1/servers/70/build'])
        ->and(array_values(array_unique(array_column($log->getArrayCopy(), 'authorization'))))->toBe(['Bearer '.VF_LC_TOKEN])
        ->and(array_values(array_unique(array_column($log->getArrayCopy(), 'host'))))->toBe([VF_LC_URL])
        ->and($log[1]['body'])->toBe(['name' => $instance->customer_name, 'email' => $instance->customer_email, 'relStr' => vfLcRelation($instance), 'sendMail' => true])
        ->and($log[2]['body'])->toBe(['packageId' => 3, 'userId' => 21, 'hypervisorId' => 2])
        ->and($log[3]['body'])->toBe(['operatingSystemId' => 7, 'email' => true]);

    $claim = vfLcClaim($instance);
    expect($claim->only(['endpoint', 'remote_id', 'remote_name', 'user_id', 'user_relation', 'plan', 'state']))->toBe([
        'endpoint' => VF_LC_ENDPOINT,
        'remote_id' => '70',
        'remote_name' => 'Elliptical Way',
        'user_id' => 21,
        'user_relation' => vfLcRelation($instance),
        'plan' => '3',
        'state' => 'active',
    ]);

    $log = vfLcFake([]);
    vfLcProvisioner()->provision(vfLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and(vfLcClaim($instance)->revision)->toBe($claim->revision);
});

test('virtfusion orchestrated provisioning activates the service without persisting secrets', function () {
    $instance = vfLcInstance();
    $log = vfLcFake(vfLcCreateScript() + ['GET /api/v1/servers/70' => [['data' => vfLcServer()]]]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->status)->toBe(ServiceInstanceStatus::Active)
        ->and($result->external_ref)->toBe('70')
        ->and(vfLcKeys($log))->toContain('GET /api/v1/servers/70');
    $persisted = json_encode([$result->fresh()->getAttributes(), vfLcClaim($instance)->getAttributes()]);
    expect($persisted)->not->toContain(VF_LC_TOKEN)
        ->and($persisted)->not->toContain(VF_LC_PASSWORD);
});

test('virtfusion reuses only a user that Agovena created for the same customer on the same endpoint', function () {
    $customer = Customer::factory()->create();
    vfLcActive($customer);
    $second = vfLcInstance($customer);
    $script = vfLcCreateScript(71);
    unset($script['POST /api/v1/users']);
    $log = vfLcFake($script);

    vfLcProvisioner()->provision(vfLcInfo($second));

    expect(vfLcKeys($log))->toBe(['GET /api/v1/packages', 'POST /api/v1/servers', 'POST /api/v1/servers/71/build'])
        ->and($log[1]['body']['userId'])->toBe(21)
        ->and(vfLcClaim($second)->only(['user_id', 'user_relation', 'state']))->toBe(['user_id' => 21, 'user_relation' => null, 'state' => 'active']);

    $stranger = vfLcInstance(Customer::factory()->create());
    $log = vfLcFake(vfLcCreateScript(72, 22));
    vfLcProvisioner()->provision(vfLcInfo($stranger));

    expect(vfLcKeys($log))->toContain('POST /api/v1/users')
        ->and(vfLcClaim($stranger)->user_id)->toBe(22);

    $elsewhere = Customer::factory()->create();
    VirtfusionAccount::query()->create([
        'service_instance_id' => vfLcInstance($elsewhere)->id,
        'endpoint' => 'https://other.example.test:443',
        'remote_id' => '5',
        'user_id' => 30,
        'state' => 'active',
    ]);
    $moved = vfLcInstance($elsewhere);
    $log = vfLcFake(vfLcCreateScript(73, 23));
    vfLcProvisioner()->provision(vfLcInfo($moved));

    expect(vfLcKeys($log))->toContain('POST /api/v1/users')
        ->and(vfLcClaim($moved)->user_id)->toBe(23);
});

test('virtfusion refuses a server id that another service already claims', function () {
    [$first, $firstClaim] = vfLcActive();
    $second = vfLcInstance();
    $log = vfLcFake(vfLcCreateScript(70, 22));

    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($second)));

    expect(vfLcKeys($log))->not->toContain('POST /api/v1/servers/70/build')
        ->and(vfLcClaim($second)->state)->toBe('creating')
        ->and(vfLcClaim($second)->remote_id)->toBe('pending:'.$second->id)
        ->and(vfLcClaim($first)->revision)->toBe($firstClaim->revision);

    $log = vfLcFake([]);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($second)));
    expect($log->count())->toBe(0);
});

test('virtfusion server create timeout is never blindly retried', function () {
    $instance = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/servers'] = [fn () => Http::failedConnection()];
    $log = vfLcFake($script);

    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($instance)));

    expect(vfLcClaim($instance)->only(['state', 'user_id', 'remote_id']))->toBe(['state' => 'unknown', 'user_id' => 21, 'remote_id' => 'pending:'.$instance->id])
        ->and(vfLcKeys($log))->not->toContain('POST /api/v1/servers/70/build');

    $log = vfLcFake([]);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($instance)));
    expect($log->count())->toBe(0);

    $other = vfLcInstance();
    $script['POST /api/v1/servers'] = [fn () => Http::failedConnection()];
    vfLcFake($script);
    $result = app(ProvisioningOrchestrator::class)->provision($other);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and(vfLcClaim($other)->state)->toBe('unknown');
});

test('virtfusion user create timeout reconciles only by the stored relation string', function () {
    $instance = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/users'] = [fn () => Http::failedConnection()];
    vfLcFake($script);

    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($instance)));
    expect(vfLcClaim($instance)->only(['state', 'user_id']))->toBe(['state' => 'unknown', 'user_id' => null]);

    $lookup = 'GET /api/v1/users/'.vfLcRelation($instance).'/byExtRelation';
    $script = vfLcCreateScript();
    unset($script['POST /api/v1/users']);
    $log = vfLcFake([$lookup => [['data' => ['id' => 21, 'extRelationId' => 0, 'name' => 'Buyer']]]] + $script);

    vfLcProvisioner()->provision(vfLcInfo($instance));

    expect(vfLcKeys($log))->toBe([$lookup, 'GET /api/v1/packages', 'POST /api/v1/servers', 'POST /api/v1/servers/70/build'])
        ->and($log[0]['query'])->toBe(['relStr' => 'true'])
        ->and(vfLcClaim($instance)->only(['state', 'user_id']))->toBe(['state' => 'active', 'user_id' => 21]);

    $absent = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/users'] = [fn () => Http::failedConnection()];
    vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($absent)));
    $log = vfLcFake(['GET /api/v1/users/'.vfLcRelation($absent).'/byExtRelation' => [Http::response(['msg' => 'user not found'], 404)]]);

    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($absent)));

    expect(vfLcKeys($log))->toBe(['GET /api/v1/users/'.vfLcRelation($absent).'/byExtRelation'])
        ->and(vfLcClaim($absent)->state)->toBe('unknown');
});

test('virtfusion build timeout reconciles by the stored server id and owner only', function () {
    $instance = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/servers/70/build'] = [fn () => Http::failedConnection()];
    vfLcFake($script);

    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($instance)));
    expect(vfLcClaim($instance)->only(['state', 'remote_id']))->toBe(['state' => 'unknown', 'remote_id' => '70']);

    $log = vfLcFake([
        'GET /api/v1/servers/70' => [['data' => vfLcServer(['built' => null])]],
        'POST /api/v1/servers/70/build' => [['data' => vfLcServer(['built' => null])]],
    ]);
    vfLcProvisioner()->provision(vfLcInfo($instance));

    expect(vfLcKeys($log))->toBe(['GET /api/v1/servers/70', 'POST /api/v1/servers/70/build'])
        ->and(vfLcClaim($instance)->state)->toBe('active');

    $built = vfLcInstance();
    $script = vfLcCreateScript(71);
    $script['POST /api/v1/servers/71/build'] = [];
    vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($built)));
    $log = vfLcFake(['GET /api/v1/servers/71' => [['data' => vfLcServer(['id' => 71])]]]);
    vfLcProvisioner()->provision(vfLcInfo($built));
    expect(vfLcKeys($log))->toBe(['GET /api/v1/servers/71'])
        ->and(vfLcClaim($built)->state)->toBe('active');

    $foreign = vfLcInstance();
    $script = vfLcCreateScript(72);
    $script['POST /api/v1/servers/72/build'] = [];
    vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($foreign)));
    $log = vfLcFake(['GET /api/v1/servers/72' => [['data' => vfLcServer(['id' => 72, 'ownerId' => 99])]]]);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($foreign)));
    expect(vfLcKeys($log))->toBe(['GET /api/v1/servers/72'])
        ->and(vfLcClaim($foreign)->state)->toBe('unknown');
});

test('virtfusion provider failure bodies with http 200 are never marked active', function () {
    $instance = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/servers'] = [['errors' => ['no capacity in hypervisor group']]];
    vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($instance)));
    expect(vfLcClaim($instance)->state)->toBe('unknown');

    $build = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/servers/70/build'] = [['msg' => 'build failed']];
    vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($build)));
    expect(vfLcClaim($build)->state)->toBe('unknown');

    VirtfusionAccount::query()->whereKey(vfLcClaim($build)->id)->update(['state' => 'active']);
    vfLcFake(['POST /api/v1/servers/70/suspend' => [['errors' => ['failed']]]]);
    vfLcRefused(fn () => vfLcProvisioner()->suspend(vfLcInfo($build)));
    expect(vfLcClaim($build)->state)->toBe('active');
});

test('virtfusion definitive rejections fail the claim and a retry keeps the recorded user', function () {
    $instance = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/servers'] = [Http::response(['errors' => ['Invalid or disabled firewall ruleset']], 422)];
    vfLcFake($script);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::Failed)
        ->and(vfLcClaim($instance)->only(['state', 'user_id']))->toBe(['state' => 'failed', 'user_id' => 21]);

    $script = vfLcCreateScript();
    unset($script['POST /api/v1/users']);
    $log = vfLcFake($script);
    vfLcProvisioner()->provision(vfLcInfo($instance));

    expect(vfLcKeys($log))->toBe(['GET /api/v1/packages', 'POST /api/v1/servers', 'POST /api/v1/servers/70/build'])
        ->and(vfLcClaim($instance)->state)->toBe('active');

    $taken = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/users'] = [Http::response(['errors' => ['user with specified email already exists']], 409)];
    $log = vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($taken)));

    expect(vfLcKeys($log))->toBe(['GET /api/v1/packages', 'POST /api/v1/users'])
        ->and(vfLcClaim($taken)->only(['state', 'user_id']))->toBe(['state' => 'failed', 'user_id' => null]);
});

test('virtfusion refuses a package that is missing or disabled before creating anything', function () {
    $instance = vfLcInstance(settings: ['package_id' => '99', 'hypervisor_group_id' => '2', 'template_id' => '7']);
    $log = vfLcFake(vfLcCreateScript());

    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($instance)));

    expect(vfLcKeys($log))->toBe(['GET /api/v1/packages'])
        ->and(vfLcClaim($instance)->state)->toBe('failed');
});

test('virtfusion refuses invalid product settings or a missing customer email before claiming', function (array $settings, ?string $email) {
    $instance = vfLcInstance(settings: $settings, email: $email);
    $log = vfLcFake(vfLcCreateScript());

    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($instance)));

    expect($log->count())->toBe(0)
        ->and(vfLcClaim($instance))->toBeNull();
})->with([
    'missing package' => [['hypervisor_group_id' => '2', 'template_id' => '7'], 'default'],
    'non numeric group' => [['package_id' => '3', 'hypervisor_group_id' => 'eu', 'template_id' => '7'], 'default'],
    'zero template' => [['package_id' => '3', 'hypervisor_group_id' => '2', 'template_id' => '0'], 'default'],
    'no email' => [['package_id' => '3', 'hypervisor_group_id' => '2', 'template_id' => '7'], ''],
]);

test('virtfusion refuses every provider call in the demo environment', function () {
    [$instance] = vfLcActive();
    $fresh = vfLcInstance();
    app()['env'] = 'demo';
    $log = vfLcFake([]);

    try {
        vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($fresh)));
        vfLcRefused(fn () => vfLcProvisioner()->suspend(vfLcInfo($instance)));
        vfLcRefused(fn () => vfLcProvisioner()->syncStatus(vfLcInfo($instance)));
        $health = vfLcProvisioner()->testServer(['api_url' => VF_LC_URL, 'api_token' => VF_LC_TOKEN]);
    } finally {
        app()['env'] = 'testing';
    }

    expect($log->count())->toBe(0)
        ->and($health->ok)->toBeFalse()
        ->and(vfLcClaim($fresh)->state)->toBe('failed')
        ->and(vfLcClaim($instance)->state)->toBe('active');
});

test('virtfusion suspend and unsuspend call the documented endpoints and track state', function () {
    [$instance] = vfLcActive();
    $log = vfLcFake([
        'POST /api/v1/servers/70/suspend' => [vfLcNoContent()],
        'POST /api/v1/servers/70/unsuspend' => [vfLcNoContent()],
    ]);

    vfLcProvisioner()->suspend(vfLcInfo($instance));
    expect(vfLcClaim($instance)->state)->toBe('suspended');
    vfLcProvisioner()->suspend(vfLcInfo($instance));
    vfLcProvisioner()->unsuspend(vfLcInfo($instance));

    expect(vfLcKeys($log))->toBe(['POST /api/v1/servers/70/suspend', 'POST /api/v1/servers/70/unsuspend'])
        ->and(vfLcClaim($instance)->state)->toBe('active');
});

test('virtfusion suspend and unsuspend failures keep the stored state', function () {
    [$instance] = vfLcActive();
    vfLcFake(['POST /api/v1/servers/70/suspend' => [Http::response(['msg' => 'pending tasks in queue'], 423)]]);
    vfLcRefused(fn () => vfLcProvisioner()->suspend(vfLcInfo($instance)));
    expect(vfLcClaim($instance)->state)->toBe('active');

    VirtfusionAccount::query()->whereKey(vfLcClaim($instance)->id)->update(['state' => 'suspended']);
    vfLcFake(['POST /api/v1/servers/70/unsuspend' => [Http::response(['msg' => 'suspend action is currently scheduled or failed'], 409)]]);
    vfLcRefused(fn () => vfLcProvisioner()->unsuspend(vfLcInfo($instance)));
    expect(vfLcClaim($instance)->state)->toBe('suspended');
});

test('virtfusion terminate deletes only the owned server and keeps the identifier claimed', function () {
    [$instance] = vfLcActive();
    vfLcFake(['DELETE /api/v1/servers/70' => [Http::response(['msg' => 'do not disturb mode is active'], 423)]]);
    vfLcRefused(fn () => vfLcProvisioner()->terminate(vfLcInfo($instance)));
    expect(vfLcClaim($instance)->state)->toBe('active');

    $log = vfLcFake(['DELETE /api/v1/servers/70' => [vfLcNoContent()]]);
    vfLcProvisioner()->terminate(vfLcInfo($instance));
    vfLcProvisioner()->terminate(vfLcInfo($instance));

    expect(vfLcKeys($log))->toBe(['DELETE /api/v1/servers/70'])
        ->and($log[0]['query'])->toBe([])
        ->and(vfLcClaim($instance)->only(['state', 'remote_id']))->toBe(['state' => 'terminated', 'remote_id' => '70'])
        ->and(vfLcProvisioner()->syncStatus(vfLcInfo($instance))->status)->toBe('terminated');

    $gone = vfLcInstance();
    vfLcFake(vfLcCreateScript(80));
    vfLcProvisioner()->provision(vfLcInfo($gone));
    vfLcFake(['DELETE /api/v1/servers/80' => [Http::response(['msg' => 'server not found'], 404)]]);
    vfLcProvisioner()->terminate(vfLcInfo($gone));
    expect(vfLcClaim($gone)->state)->toBe('terminated');
});

test('virtfusion terminate never touches unverified claims and closes rejected or unclaimed services without network', function () {
    $unknown = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/servers/70/build'] = [fn () => Http::failedConnection()];
    vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($unknown)));

    $rejected = vfLcInstance();
    $script = vfLcCreateScript(71);
    $script['POST /api/v1/servers'] = [Http::response(['errors' => ['Invalid request']], 422)];
    vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($rejected)));

    $log = vfLcFake([]);
    vfLcRefused(fn () => vfLcProvisioner()->terminate(vfLcInfo($unknown)));
    vfLcProvisioner()->terminate(vfLcInfo($rejected));
    vfLcProvisioner()->terminate(vfLcInfo(vfLcInstance()));

    expect($log->count())->toBe(0)
        ->and(vfLcClaim($unknown)->state)->toBe('unknown')
        ->and(vfLcClaim($rejected)->state)->toBe('terminated');
});

test('virtfusion plan change moves the owned server to an enabled package with the documented call', function () {
    [$instance] = vfLcActive();
    $log = vfLcFake([
        'GET /api/v1/packages' => [vfLcPackages(3, 4)],
        'PUT /api/v1/servers/70/package/4' => [['info' => ['CPU cores not updated. It matches the current value']]],
    ]);

    vfLcProvisioner()->changePlan(vfLcInfo($instance), ['provider_settings' => ['package_id' => '4']]);
    vfLcProvisioner()->changePlan(vfLcInfo($instance), ['provider_settings' => ['package_id' => '4']]);

    expect(vfLcKeys($log))->toBe(['GET /api/v1/packages', 'PUT /api/v1/servers/70/package/4'])
        ->and($log[1]['body'])->toMatchArray(['cpu' => true, 'memory' => true, 'primaryDiskSize' => true])
        ->and(vfLcClaim($instance)->plan)->toBe('4')
        ->and(vfLcClaim($instance)->state)->toBe('active');
});

test('virtfusion plan change refuses missing, disabled or invalid packages without changing the server', function (array $plan, array $script) {
    [$instance] = vfLcActive();
    $log = vfLcFake($script);

    vfLcRefused(fn () => vfLcProvisioner()->changePlan(vfLcInfo($instance), $plan));

    expect(vfLcKeys($log))->not->toContain('PUT /api/v1/servers/70/package/99')
        ->and(vfLcKeys($log))->not->toContain('PUT /api/v1/servers/70/package/5')
        ->and(vfLcClaim($instance)->plan)->toBe('3');
})->with([
    'disabled package' => [['provider_settings' => ['package_id' => '99']], ['GET /api/v1/packages' => [vfLcPackages(3, 4)]]],
    'unknown package' => [['provider_settings' => ['package_id' => '5']], ['GET /api/v1/packages' => [vfLcPackages(3, 4)]]],
    'no package' => [['provider_settings' => []], []],
    'non numeric package' => [['provider_settings' => ['package_id' => '4; drop']], []],
]);

test('virtfusion plan change keeps the stored plan when the provider refuses or answers unexpectedly', function (mixed $response) {
    [$instance] = vfLcActive();
    vfLcFake([
        'GET /api/v1/packages' => [vfLcPackages(3, 4)],
        'PUT /api/v1/servers/70/package/4' => [$response],
    ]);

    vfLcRefused(fn () => vfLcProvisioner()->changePlan(vfLcInfo($instance), ['provider_settings' => ['package_id' => '4']]));

    expect(vfLcClaim($instance)->plan)->toBe('3');
})->with([
    'locked' => fn () => Http::response(['msg' => 'pending tasks in queue'], 423),
    'error body with 200' => [['error' => 'failed']],
    'empty 204' => fn () => Http::response('', 204),
]);

test('virtfusion plan change never touches unverified or terminated claims', function () {
    [$instance] = vfLcActive();
    VirtfusionAccount::query()->whereKey(vfLcClaim($instance)->id)->update(['state' => 'unknown']);
    $log = vfLcFake([]);

    vfLcRefused(fn () => vfLcProvisioner()->changePlan(vfLcInfo($instance), ['provider_settings' => ['package_id' => '4']]));
    VirtfusionAccount::query()->whereKey(vfLcClaim($instance)->id)->update(['state' => 'terminated']);
    vfLcRefused(fn () => vfLcProvisioner()->changePlan(vfLcInfo($instance), ['provider_settings' => ['package_id' => '4']]));

    expect($log->count())->toBe(0)
        ->and(vfLcClaim($instance)->plan)->toBe('3');
});

test('virtfusion sync maps the documented server flags and fails closed otherwise', function () {
    [$instance] = vfLcActive();
    $log = vfLcFake(['GET /api/v1/servers/70' => [
        ['data' => vfLcServer()],
        ['data' => vfLcServer(['suspended' => true])],
        ['data' => vfLcServer(['built' => null])],
        ['data' => vfLcServer(['buildFailed' => true])],
        ['data' => vfLcServer(['suspended' => 'no'])],
        ['data' => vfLcServer(['ownerId' => 99])],
        ['data' => vfLcServer(['id' => 71])],
        Http::response(['msg' => 'server not found'], 404),
    ]]);

    $active = vfLcProvisioner()->syncStatus(vfLcInfo($instance));
    expect($active->status)->toBe('active')
        ->and($active->externalRef)->toBe('70')
        ->and($active->meta['provider_mapping'])->toBe(['provider_id' => '70', 'user_id' => 21, 'package_id' => '3']);

    expect(vfLcProvisioner()->syncStatus(vfLcInfo($instance))->status)->toBe('suspended')
        ->and(vfLcClaim($instance)->state)->toBe('suspended')
        ->and(vfLcProvisioner()->syncStatus(vfLcInfo($instance))->status)->toBe('provisioning')
        ->and(vfLcClaim($instance)->state)->toBe('active');

    foreach (range(1, 5) as $ignored) {
        vfLcRefused(fn () => vfLcProvisioner()->syncStatus(vfLcInfo($instance)));
    }

    expect($log->count())->toBe(8)
        ->and($log[0]['query'])->toBe([])
        ->and(vfLcClaim($instance)->state)->toBe('active');
});

test('virtfusion sync of an unverified claim refuses without network and reports absence otherwise', function () {
    $unknown = vfLcInstance();
    $script = vfLcCreateScript();
    $script['POST /api/v1/servers'] = [fn () => Http::failedConnection()];
    vfLcFake($script);
    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($unknown)));

    $log = vfLcFake([]);
    vfLcRefused(fn () => vfLcProvisioner()->syncStatus(vfLcInfo($unknown)));
    $absent = vfLcProvisioner()->syncStatus(vfLcInfo(vfLcInstance()));

    expect($log->count())->toBe(0)
        ->and($absent->meta['provider_reconciliation'])->toBe('absent');
});

test('virtfusion lifecycle is refused without network when the endpoint is re-pointed', function () {
    [$instance] = vfLcActive();
    vfLcPointAt('https://other.example.test');
    $log = vfLcFake([]);

    foreach (['provision', 'suspend', 'unsuspend', 'terminate', 'syncStatus'] as $operation) {
        $exception = vfLcRefused(fn () => vfLcProvisioner()->{$operation}(vfLcInfo($instance)));
        expect($exception->errors()['instance'][0])->toBe(__('virtfusion::messages.errors.endpoint_changed'));
    }

    expect($log->count())->toBe(0)
        ->and(vfLcClaim($instance)->state)->toBe('active');
});

test('virtfusion hand written provider mapping without a claim row is refused without network', function () {
    $instance = vfLcInstance(meta: ['provider_mapping' => ['provider_id' => '70']], externalRef: '70');
    $log = vfLcFake(vfLcCreateScript());

    foreach (['provision', 'suspend', 'unsuspend', 'terminate', 'syncStatus'] as $operation) {
        vfLcRefused(fn () => vfLcProvisioner()->{$operation}(vfLcInfo($instance)));
    }

    expect($log->count())->toBe(0)
        ->and(vfLcClaim($instance))->toBeNull();
});

test('virtfusion stale revision between read and write never overwrites the row', function () {
    [$instance, $claim] = vfLcActive();
    vfLcFake(['POST /api/v1/servers/70/suspend' => [function () use ($claim) {
        DB::table('virtfusion_accounts')->where('id', $claim->id)->update(['revision' => 9]);

        return Http::response('', 204);
    }]]);

    vfLcRefused(fn () => vfLcProvisioner()->suspend(vfLcInfo($instance)));
    expect(vfLcClaim($instance)->only(['state', 'revision']))->toBe(['state' => 'active', 'revision' => 9]);

    $fresh = vfLcInstance();
    $script = vfLcCreateScript(71);
    $script['POST /api/v1/servers/71/build'] = [function () use ($fresh) {
        DB::table('virtfusion_accounts')->where('service_instance_id', $fresh->id)->update(['state' => 'failed', 'revision' => 5]);

        return Http::response(['data' => vfLcServer(['id' => 71])], 200);
    }];
    vfLcFake($script);

    vfLcRefused(fn () => vfLcProvisioner()->provision(vfLcInfo($fresh)));
    expect(vfLcClaim($fresh)->only(['state', 'revision']))->toBe(['state' => 'failed', 'revision' => 5]);
});

test('virtfusion never leaks the api token or user password', function () {
    [$instance] = vfLcActive();
    vfLcFake(['GET /api/v1/servers/70' => [['data' => vfLcServer()], Http::response(['error' => VF_LC_TOKEN], 500)]]);

    $info = vfLcProvisioner()->syncStatus(vfLcInfo($instance));
    $exception = vfLcRefused(fn () => vfLcProvisioner()->syncStatus(vfLcInfo($instance)));
    $panel = vfLcProvisioner()->panel(vfLcInfo($instance));
    $output = json_encode([
        $info->meta, $info->serverSettings, $info->providerSettings, $info->externalRef, $exception->errors(), $exception->getMessage(),
        $panel, VirtfusionAccount::query()->get()->toArray(), $instance->fresh()->meta,
    ]);

    expect($output)->not->toContain(VF_LC_TOKEN)
        ->and($output)->not->toContain(VF_LC_PASSWORD);
});

test('virtfusion panel shows the claimed server without network or power actions', function () {
    [$instance] = vfLcActive();
    $log = vfLcFake([]);

    $panel = vfLcProvisioner()->panel(vfLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and(collect($panel?->fields)->pluck('value')->all())->toContain('70', '3', 'Elliptical Way')
        ->and(vfLcProvisioner()->actions(vfLcInfo($instance)))->toBe([])
        ->and(vfLcProvisioner()->panel(vfLcInfo(vfLcInstance())))->toBeNull();
});

test('virtfusion health uses the documented read-only connect endpoint and fails closed', function () {
    $settings = ['api_url' => VF_LC_URL, 'api_token' => VF_LC_TOKEN];
    $log = vfLcFake(['GET /api/v1/connect' => [[], ['errors' => ['nope']], Http::response('', 401)]]);

    expect(vfLcProvisioner()->testServer($settings)->ok)->toBeTrue()
        ->and(vfLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(vfLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(vfLcProvisioner()->testServer(['api_url' => VF_LC_URL])->ok)->toBeFalse()
        ->and(vfLcKeys($log))->toBe(['GET /api/v1/connect', 'GET /api/v1/connect', 'GET /api/v1/connect'])
        ->and($log[0]['authorization'])->toBe('Bearer '.VF_LC_TOKEN);
});
