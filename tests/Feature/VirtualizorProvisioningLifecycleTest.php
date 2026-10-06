<?php

declare(strict_types=1);

use Agovena\Extensions\Virtualizor\VirtualizorAccount;
use Agovena\Extensions\Virtualizor\VirtualizorEndpoint;
use Agovena\Extensions\Virtualizor\VirtualizorProvisioner;
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

const VZ_LC_KEY = 'vz-key-SECRET-41f2a8';
const VZ_LC_PASS = 'vz-pass-SECRET-9c1d77';
const VZ_LC_URL = 'https://vz.example.test:4085';
const VZ_LC_ENDPOINT = 'https://vz.example.test:4085';
const VZ_LC_SETTINGS = ['plan_id' => '3', 'os_id' => '7', 'server_group' => '2'];

beforeEach(function (): void {
    installAndEnableModule('provisioning');
    app(ExtensionManager::class)->discover();
    installAndEnableExtension('virtualizor');
    app()->forgetInstance(VirtualizorProvisioner::class);
    vzLcPointAt(VZ_LC_URL);
    vzLcRegisterFake();
});

function vzLcPointAt(string $url): void
{
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('virtualizor', 'api_url', $url);
    $settings->set('virtualizor', 'api_token', VZ_LC_KEY, secret: true);
    $settings->set('virtualizor', 'api_secret', VZ_LC_PASS, secret: true);
    $settings->set('virtualizor', 'verify_tls', true);
    $settings->set('virtualizor', 'timeout', '20');
}

function vzLcProvisioner(): VirtualizorProvisioner
{
    return app(VirtualizorProvisioner::class);
}

/** @param array<string, mixed> $settings @param array<string, mixed> $meta */
function vzLcInstance(?Customer $customer = null, array $settings = VZ_LC_SETTINGS, array $meta = [], ?string $externalRef = null, ?string $email = 'default'): ServiceInstance
{
    static $sequence = 0;
    $sequence++;
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-VZLC-'.$sequence.'-'.bin2hex(random_bytes(3)),
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'virtualizor',
        'customer_id' => $customer?->id,
        'customer_email' => $email === 'default' ? ($customer?->email ?? 'buyer'.$sequence.'@example.test') : $email,
        'customer_name' => 'Buyer Number '.$sequence,
        'external_ref' => $externalRef,
        'meta' => array_merge(['label' => 'VPS'], $meta),
    ]);
    app(ServiceInstanceRuntimeSecretStore::class)->put($instance->id, null, $settings);

    return $instance->fresh();
}

function vzLcInfo(ServiceInstance $instance): ServiceInstanceInfo
{
    return EloquentProvisionedServiceResolver::info($instance->fresh() ?? $instance);
}

/** @return array<string, mixed> */
function vzLcPlan(int $id, string $virt = 'kvm', string $enabled = '1'): array
{
    return [
        'plid' => (string) $id, 'plan_name' => 'Plan '.$id, 'virt' => $virt, 'ips' => '1', 'ips6' => '0',
        'space' => '20', 'ram' => '2048', 'swap' => '0', 'bandwidth' => '1000', 'cores' => '2', 'osid' => '270', 'is_enabled' => $enabled,
    ];
}

/** @return array<string, mixed> */
function vzLcPlans(): array
{
    return ['title' => 'Plans', 'plans' => [
        '3' => vzLcPlan(3), '4' => vzLcPlan(4), '5' => vzLcPlan(5, 'openvz'), '9' => vzLcPlan(9, 'kvm', '0'),
    ], 'timenow' => 1535713000];
}

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function vzLcVs(array $overrides = []): array
{
    $vps = array_merge([
        'vpsid' => '70', 'vps_name' => 'v1001', 'uid' => '21', 'plid' => '3', 'virt' => 'kvm',
        'hostname' => 'vps70.example.test', 'suspended' => '0', 'email' => 'buyer@example.test',
    ], $overrides);

    return ['title' => 'Virtual Servers', 'vs' => [$vps['vpsid'] => $vps]];
}

/** @return array<string, mixed> */
function vzLcCreated(int $vpsId = 70, int $userId = 21): array
{
    return ['error' => [], 'vs_info' => [
        'vpsid' => (string) $vpsId, 'vps_name' => 'v1001', 'uid' => (string) $userId, 'plid' => '3', 'virt' => 'kvm', 'hostname' => 'vps70.example.test',
    ]];
}

function vzLcState(): stdClass
{
    static $state = null;

    return $state ??= new stdClass;
}

function vzLcRegisterFake(): void
{
    vzLcFake([]);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $state = vzLcState();
        $parts = parse_url($request->url());
        parse_str((string) ($parts['query'] ?? ''), $query);
        $operation = Arr::first(['suspend', 'unsuspend', 'delete', 'vpsid'], static fn (string $name): bool => ($query['act'] ?? '') === 'vs' && array_key_exists($name, $query));
        $key = $request->method().' '.($query['act'] ?? '').($operation === null ? '' : ':'.$operation);
        $state->log[] = [
            'key' => $key,
            'url' => ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').':'.($parts['port'] ?? '').($parts['path'] ?? ''),
            'query' => $query,
            'body' => $request->method() === 'GET' ? [] : $request->data(),
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
 * Replaces the Virtualizor script. Keys are "METHOD act[:operation]"; arrays are 200 JSON bodies.
 *
 * @param  array<string, list<mixed>>  $script
 */
function vzLcFake(array $script): ArrayObject
{
    $state = vzLcState();
    $state->script = $script;
    $state->log = new ArrayObject;

    return $state->log;
}

/** @return list<string> */
function vzLcKeys(ArrayObject $log): array
{
    return array_values(array_map(static fn (array $entry): string => $entry['key'], $log->getArrayCopy()));
}

/** @return array<string, list<mixed>> */
function vzLcCreateScript(int $vpsId = 70, int $userId = 21): array
{
    return [
        'POST plans' => [vzLcPlans()],
        'POST users' => [['title' => 'Users', 'users' => []]],
        'POST addvs' => [vzLcCreated($vpsId, $userId)],
    ];
}

function vzLcClaim(ServiceInstance $instance): ?VirtualizorAccount
{
    return VirtualizorAccount::query()->where('service_instance_id', $instance->id)->first();
}

/** @return array{0: ServiceInstance, 1: VirtualizorAccount} */
function vzLcActive(?Customer $customer = null): array
{
    $instance = vzLcInstance($customer);
    vzLcFake(vzLcCreateScript());
    vzLcProvisioner()->provision(vzLcInfo($instance));

    return [$instance, vzLcClaim($instance)];
}

function vzLcRefused(callable $operation): ValidationException
{
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('instance');

        return $exception;
    }

    throw new RuntimeException('Expected the Virtualizor operation to be refused.');
}

test('virtualizor migration creates the claim table with unique identity columns and is reversible', function () {
    expect(Schema::hasColumns('virtualizor_accounts', [
        'id', 'service_instance_id', 'endpoint', 'remote_id', 'remote_name', 'user_id', 'plan', 'state', 'revision', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $row = ['endpoint' => VZ_LC_ENDPOINT, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()];
    DB::table('virtualizor_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => '70']);

    expect(fn () => DB::table('virtualizor_accounts')->insert($row + ['service_instance_id' => 900002, 'remote_id' => '70']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('virtualizor_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => '72']))
        ->toThrow(QueryException::class)
        ->and(DB::table('virtualizor_accounts')->where('service_instance_id', 900001)->value('revision'))->toBe(0);

    DB::table('virtualizor_accounts')->insert(['endpoint' => 'https://other.example.test:4085'] + $row + ['service_instance_id' => 900004, 'remote_id' => '70']);
    expect(DB::table('virtualizor_accounts')->count())->toBe(2);

    $migration = require config('agovena.packages.optional_packages_path').'/extensions/provisioning/virtualizor/database/migrations/2026_10_05_000000_create_virtualizor_accounts_table.php';
    $migration->up();
    $migration->down();
    expect(Schema::hasTable('virtualizor_accounts'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('virtualizor_accounts'))->toBeTrue();
});

test('virtualizor endpoint identity is normalized and rejects paths and credentials', function () {
    expect(VirtualizorEndpoint::normalize('HTTPS://VZ.Example.TEST:4085/'))->toBe(VZ_LC_ENDPOINT)
        ->and(VirtualizorEndpoint::normalize('https://vz.example.test'))->toBe('https://vz.example.test:443');

    foreach (['https://vz.example.test:4085/index.php', 'https://user:pass@vz.example.test', 'ftp://vz.example.test', 'https://vz.example.test?adminapikey=x', ''] as $url) {
        expect(fn () => VirtualizorEndpoint::normalize($url))->toThrow(ServerProviderException::class);
    }
});

test('virtualizor create sends the documented calls once and a retry is idempotent', function () {
    $instance = vzLcInstance();
    $log = vzLcFake(vzLcCreateScript());

    vzLcProvisioner()->provision(vzLcInfo($instance));

    expect(vzLcKeys($log))->toBe(['POST plans', 'POST users', 'POST addvs'])
        ->and(array_values(array_unique(array_column($log->getArrayCopy(), 'url'))))->toBe([VZ_LC_URL.'/index.php']);
    foreach ($log as $entry) {
        expect(Arr::only($entry['query'], ['api', 'adminapikey', 'adminapipass']))->toBe(['api' => 'json', 'adminapikey' => VZ_LC_KEY, 'adminapipass' => VZ_LC_PASS]);
    }

    $body = $log[2]['body'];
    expect($log[0]['body'])->toBe(['reslen' => '1000'])
        ->and($log[1]['body'])->toBe(['email' => $instance->customer_email])
        ->and(Arr::except($body, ['rootpass', 'user_pass']))->toBe([
            'addvps' => '1',
            'virt' => 'kvm',
            'plid' => '3',
            'osid' => '7',
            'node_select' => '1',
            'server_group' => '2',
            'hostname' => 'agovena-'.$instance->id,
            'user_email' => $instance->customer_email,
            'fname' => 'Buyer',
            'lname' => 'Number '.str_replace('Buyer Number ', '', $instance->customer_name),
            'num_ips' => '1',
            'space' => '20',
            'ram' => '2048',
            'bandwidth' => '1000',
            'cores' => '2',
        ])
        ->and(strlen($body['rootpass']))->toBe(24)
        ->and(strlen($body['user_pass']))->toBe(24)
        ->and($body['rootpass'])->not->toBe($body['user_pass']);

    $claim = vzLcClaim($instance);
    expect($claim->only(['endpoint', 'remote_id', 'remote_name', 'user_id', 'plan', 'state']))->toBe([
        'endpoint' => VZ_LC_ENDPOINT,
        'remote_id' => '70',
        'remote_name' => 'vps70.example.test',
        'user_id' => 21,
        'plan' => '3',
        'state' => 'active',
    ]);

    $log = vzLcFake([]);
    vzLcProvisioner()->provision(vzLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and(vzLcClaim($instance)->revision)->toBe($claim->revision);
});

test('virtualizor orchestrated provisioning activates the service without persisting secrets', function () {
    $instance = vzLcInstance();
    $log = vzLcFake(vzLcCreateScript() + ['GET vs:vpsid' => [vzLcVs()]]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->status)->toBe(ServiceInstanceStatus::Active)
        ->and($result->external_ref)->toBe('70')
        ->and(vzLcKeys($log))->toContain('GET vs:vpsid');
    $passwords = [$log[2]['body']['rootpass'], $log[2]['body']['user_pass']];
    $persisted = json_encode([$result->fresh()->getAttributes(), vzLcClaim($instance)->getAttributes()]);
    foreach ([VZ_LC_KEY, VZ_LC_PASS, ...$passwords] as $secret) {
        expect($persisted)->not->toContain($secret);
    }
});

test('virtualizor reuses only a user that Agovena created for the same customer and refuses foreign users', function () {
    $customer = Customer::factory()->create();
    vzLcActive($customer);
    $second = vzLcInstance($customer);
    $script = vzLcCreateScript(71);
    unset($script['POST users']);
    $log = vzLcFake($script);

    vzLcProvisioner()->provision(vzLcInfo($second));

    expect(vzLcKeys($log))->toBe(['POST plans', 'POST addvs'])
        ->and($log[1]['body']['uid'])->toBe('21')
        ->and(vzLcClaim($second)->only(['user_id', 'remote_id', 'state']))->toBe(['user_id' => 21, 'remote_id' => '71', 'state' => 'active']);

    $third = vzLcInstance($customer);
    vzLcFake(['POST plans' => [vzLcPlans()], 'POST addvs' => [vzLcCreated(72, 22)]]);
    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($third)));
    expect(vzLcClaim($third)->only(['state', 'remote_id']))->toBe(['state' => 'unknown', 'remote_id' => 'pending:'.$third->id]);

    $foreign = vzLcInstance(email: 'taken@example.test');
    $script = vzLcCreateScript(73, 23);
    $script['POST users'] = [['users' => ['55' => ['uid' => '55', 'email' => 'TAKEN@example.test']]]];
    $log = vzLcFake($script);

    $exception = vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($foreign)));

    expect($exception->errors()['instance'][0])->toBe(__('virtualizor::messages.errors.user_exists'))
        ->and(vzLcKeys($log))->toBe(['POST plans', 'POST users'])
        ->and(vzLcClaim($foreign)->only(['state', 'user_id']))->toBe(['state' => 'failed', 'user_id' => null]);
});

test('virtualizor refuses a vps id that another service already claims', function () {
    [$first, $firstClaim] = vzLcActive();
    $second = vzLcInstance();
    vzLcFake(vzLcCreateScript(70, 22));

    $exception = vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($second)));

    expect($exception->errors()['instance'][0])->toBe(__('virtualizor::messages.errors.claimed'))
        ->and(vzLcClaim($second)->only(['state', 'remote_id']))->toBe(['state' => 'creating', 'remote_id' => 'pending:'.$second->id])
        ->and(vzLcClaim($first)->revision)->toBe($firstClaim->revision);

    $log = vzLcFake([]);
    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($second)));
    expect($log->count())->toBe(0)
        ->and(vzLcClaim($second)->state)->toBe('unknown');
});

test('virtualizor create timeout or unexpected answer is never blindly retried', function (mixed $answer) {
    $instance = vzLcInstance();
    $script = vzLcCreateScript();
    $script['POST addvs'] = [$answer];
    vzLcFake($script);

    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($instance)));
    expect(vzLcClaim($instance)->only(['state', 'remote_id', 'user_id']))->toBe(['state' => 'unknown', 'remote_id' => 'pending:'.$instance->id, 'user_id' => null]);

    $log = vzLcFake(vzLcCreateScript());
    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($instance)));
    expect($log->count())->toBe(0);

    $other = vzLcInstance();
    $script['POST addvs'] = [$answer];
    vzLcFake($script);
    $result = app(ProvisioningOrchestrator::class)->provision($other);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and(vzLcClaim($other)->state)->toBe('unknown');
})->with([
    'timeout' => [fn () => Http::failedConnection()],
    'server error' => [fn () => Http::response('', 500)],
    'missing vps info' => [['error' => []]],
    'html page' => [fn () => Http::response('<html>login</html>', 200)],
]);

test('virtualizor documented error arrays with http 200 fail the claim and a retry creates again', function () {
    $instance = vzLcInstance();
    $script = vzLcCreateScript();
    $script['POST addvs'] = [['error' => ['hostname' => 'The hostname is invalid'], 'vs_info' => null]];
    vzLcFake($script);

    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($instance)));
    expect(vzLcClaim($instance)->state)->toBe('failed');

    $log = vzLcFake(vzLcCreateScript());
    vzLcProvisioner()->provision(vzLcInfo($instance));

    expect(vzLcKeys($log))->toBe(['POST plans', 'POST users', 'POST addvs'])
        ->and(vzLcClaim($instance)->only(['state', 'remote_id']))->toBe(['state' => 'active', 'remote_id' => '70']);
});

test('virtualizor refuses a plan that is missing or disabled before creating anything', function (string $planId) {
    $instance = vzLcInstance(settings: ['plan_id' => $planId] + VZ_LC_SETTINGS);
    $log = vzLcFake(vzLcCreateScript());

    $exception = vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($instance)));

    expect($exception->errors()['instance'][0])->toBe(__('virtualizor::messages.errors.plan_unavailable'))
        ->and(vzLcKeys($log))->toBe(['POST plans'])
        ->and(vzLcClaim($instance)->state)->toBe('failed');
})->with(['missing' => '8', 'disabled' => '9']);

test('virtualizor refuses invalid product settings or a missing customer email before claiming', function (array $settings, ?string $email) {
    $instance = vzLcInstance(settings: $settings, email: $email);
    $log = vzLcFake(vzLcCreateScript());

    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($instance)));

    expect($log->count())->toBe(0)
        ->and(vzLcClaim($instance))->toBeNull();
})->with([
    'missing plan' => [['os_id' => '7', 'server_group' => '2'], 'default'],
    'non numeric os' => [['plan_id' => '3', 'os_id' => 'debian', 'server_group' => '2'], 'default'],
    'negative group' => [['plan_id' => '3', 'os_id' => '7', 'server_group' => '-1'], 'default'],
    'no email' => [VZ_LC_SETTINGS, ''],
]);

test('virtualizor refuses every provider call in the demo environment', function () {
    [$instance] = vzLcActive();
    $fresh = vzLcInstance();
    app()['env'] = 'demo';
    $log = vzLcFake([]);

    try {
        vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($fresh)));
        vzLcRefused(fn () => vzLcProvisioner()->suspend(vzLcInfo($instance)));
        vzLcRefused(fn () => vzLcProvisioner()->syncStatus(vzLcInfo($instance)));
        $health = vzLcProvisioner()->testServer(['api_url' => VZ_LC_URL, 'api_token' => VZ_LC_KEY, 'api_secret' => VZ_LC_PASS]);
    } finally {
        app()['env'] = 'testing';
    }

    expect($log->count())->toBe(0)
        ->and($health->ok)->toBeFalse()
        ->and(vzLcClaim($fresh)->state)->toBe('failed')
        ->and(vzLcClaim($instance)->state)->toBe('active');
});

test('virtualizor suspend and unsuspend call the documented actions and track state', function () {
    [$instance] = vzLcActive();
    $log = vzLcFake([
        'GET vs:suspend' => [['title' => 'Virtual Servers', 'done' => 1, 'done_msg' => 'VPS Suspended']],
        'GET vs:unsuspend' => [['title' => 'Virtual Servers', 'done' => 1, 'done_msg' => 'VPS Unsuspended']],
    ]);

    vzLcProvisioner()->suspend(vzLcInfo($instance));
    vzLcProvisioner()->suspend(vzLcInfo($instance));
    expect(vzLcClaim($instance)->state)->toBe('suspended');

    vzLcProvisioner()->unsuspend(vzLcInfo($instance));
    vzLcProvisioner()->unsuspend(vzLcInfo($instance));

    expect(vzLcKeys($log))->toBe(['GET vs:suspend', 'GET vs:unsuspend'])
        ->and($log[0]['query']['suspend'])->toBe('70')
        ->and($log[1]['query']['unsuspend'])->toBe('70')
        ->and(vzLcClaim($instance)->state)->toBe('active');
});

test('virtualizor suspend failures keep the stored state', function (mixed $answer) {
    [$instance, $claim] = vzLcActive();
    vzLcFake(['GET vs:suspend' => [$answer]]);

    vzLcRefused(fn () => vzLcProvisioner()->suspend(vzLcInfo($instance)));

    expect(vzLcClaim($instance)->only(['state', 'revision']))->toBe(['state' => 'active', 'revision' => $claim->revision]);
})->with([
    'no done flag' => [['title' => 'Virtual Servers', 'done' => null]],
    'done zero' => [['done' => 0]],
    'error array' => [['done' => 1, 'error' => ['Could not suspend']]],
    'unauthorized' => [fn () => Http::response('', 403)],
    'timeout' => [fn () => Http::failedConnection()],
]);

test('virtualizor terminate deletes only the owned vps and keeps the identifier claimed', function () {
    [$instance] = vzLcActive();
    $log = vzLcFake(['GET vs:vpsid' => [vzLcVs()], 'GET vs:delete' => [['title' => 'Virtual Servers', 'done' => true]]]);

    vzLcProvisioner()->terminate(vzLcInfo($instance));
    vzLcProvisioner()->terminate(vzLcInfo($instance));

    expect(vzLcKeys($log))->toBe(['GET vs:vpsid', 'GET vs:delete'])
        ->and($log[0]['query']['vpsid'])->toBe('70')
        ->and($log[1]['query']['delete'])->toBe('70')
        ->and(vzLcClaim($instance)->only(['state', 'remote_id']))->toBe(['state' => 'terminated', 'remote_id' => '70']);

    $foreign = vzLcInstance();
    vzLcFake(vzLcCreateScript(71));
    vzLcProvisioner()->provision(vzLcInfo($foreign));
    $log = vzLcFake(['GET vs:vpsid' => [vzLcVs(['vpsid' => '71', 'uid' => '99']), vzLcVs(['vpsid' => '71'])], 'GET vs:delete' => [['done' => false]]]);

    vzLcRefused(fn () => vzLcProvisioner()->terminate(vzLcInfo($foreign)));
    vzLcRefused(fn () => vzLcProvisioner()->terminate(vzLcInfo($foreign)));

    expect(vzLcKeys($log))->toBe(['GET vs:vpsid', 'GET vs:vpsid', 'GET vs:delete'])
        ->and(vzLcClaim($foreign)->state)->toBe('active');
});

test('virtualizor terminate never touches unverified claims and closes failed or unclaimed services without network', function () {
    $unknown = vzLcInstance();
    $script = vzLcCreateScript();
    $script['POST addvs'] = [fn () => Http::failedConnection()];
    vzLcFake($script);
    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($unknown)));

    $failed = vzLcInstance(settings: ['plan_id' => '9'] + VZ_LC_SETTINGS);
    vzLcFake(vzLcCreateScript());
    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($failed)));

    $log = vzLcFake([]);
    vzLcRefused(fn () => vzLcProvisioner()->terminate(vzLcInfo($unknown)));
    vzLcProvisioner()->terminate(vzLcInfo($failed));
    vzLcProvisioner()->terminate(vzLcInfo(vzLcInstance()));

    expect($log->count())->toBe(0)
        ->and(vzLcClaim($unknown)->state)->toBe('unknown')
        ->and(vzLcClaim($failed)->state)->toBe('terminated');
});

test('virtualizor plan change applies an existing plan and verifies it afterwards', function () {
    [$instance] = vzLcActive();
    $log = vzLcFake([
        'POST plans' => [vzLcPlans()],
        'GET vs:vpsid' => [vzLcVs(), vzLcVs(['plid' => '4'])],
        'POST managevps' => [['title' => 'Manage VPS', 'done' => ['done' => true], 'error' => []]],
    ]);

    vzLcProvisioner()->changePlan(vzLcInfo($instance), ['id' => 'pro', 'provider_settings' => ['plan_id' => '4']]);

    expect(vzLcKeys($log))->toBe(['POST plans', 'GET vs:vpsid', 'POST managevps', 'GET vs:vpsid'])
        ->and($log[2]['query']['vpsid'])->toBe('70')
        ->and($log[2]['body'])->toBe(['vpsid' => '70', 'plid' => '4', 'apply_plan' => '1', 'editvps' => '1'])
        ->and(vzLcClaim($instance)->plan)->toBe('4');

    $log = vzLcFake([]);
    vzLcProvisioner()->changePlan(vzLcInfo($instance), ['provider_settings' => ['plan_id' => '4']]);
    expect($log->count())->toBe(0);
});

test('virtualizor plan change refuses missing, disabled, foreign or invalid plans without managing the vps', function (array $plan, array $script) {
    [$instance] = vzLcActive();
    $log = vzLcFake($script);

    vzLcRefused(fn () => vzLcProvisioner()->changePlan(vzLcInfo($instance), $plan));

    expect(vzLcKeys($log))->not->toContain('POST managevps')
        ->and(vzLcClaim($instance)->plan)->toBe('3');
})->with([
    'missing plan' => [['provider_settings' => ['plan_id' => '8']], ['POST plans' => [vzLcPlans()]]],
    'disabled plan' => [['provider_settings' => ['plan_id' => '9']], ['POST plans' => [vzLcPlans()]]],
    'other virtualization' => [['provider_settings' => ['plan_id' => '5']], ['POST plans' => [vzLcPlans()], 'GET vs:vpsid' => [vzLcVs()]]],
    'foreign owner' => [['provider_settings' => ['plan_id' => '4']], ['POST plans' => [vzLcPlans()], 'GET vs:vpsid' => [vzLcVs(['uid' => '99'])]]],
    'string plan' => [['id' => 'pro'], []],
    'zero plan' => [['provider_settings' => ['plan_id' => '0']], []],
]);

test('virtualizor plan change keeps the stored plan when the provider refuses or does not apply it', function (array $manage, array $after) {
    [$instance] = vzLcActive();
    vzLcFake(['POST plans' => [vzLcPlans()], 'GET vs:vpsid' => [vzLcVs(), $after], 'POST managevps' => [$manage]]);

    vzLcRefused(fn () => vzLcProvisioner()->changePlan(vzLcInfo($instance), ['provider_settings' => ['plan_id' => '4']]));

    expect(vzLcClaim($instance)->plan)->toBe('3');
})->with([
    'error array' => [['done' => ['done' => true], 'error' => ['plid' => 'Invalid plan']], vzLcVs(['plid' => '4'])],
    'no done flag' => [['title' => 'Manage VPS', 'error' => []], vzLcVs(['plid' => '4'])],
    'plan not applied' => [['done' => ['done' => true], 'error' => []], vzLcVs()],
]);

test('virtualizor plan change never touches unverified or terminated claims', function () {
    $unknown = vzLcInstance();
    $script = vzLcCreateScript();
    $script['POST addvs'] = [fn () => Http::failedConnection()];
    vzLcFake($script);
    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($unknown)));

    $log = vzLcFake([]);
    vzLcRefused(fn () => vzLcProvisioner()->changePlan(vzLcInfo($unknown), ['provider_settings' => ['plan_id' => '4']]));
    vzLcRefused(fn () => vzLcProvisioner()->changePlan(vzLcInfo(vzLcInstance()), ['provider_settings' => ['plan_id' => '4']]));

    expect($log->count())->toBe(0);
});

test('virtualizor sync maps the documented suspension flag and fails closed otherwise', function () {
    [$instance] = vzLcActive();
    $log = vzLcFake(['GET vs:vpsid' => [
        vzLcVs(),
        vzLcVs(['suspended' => '1']),
        vzLcVs(['hostname' => 'renamed.example.test']),
        vzLcVs(['uid' => '99']),
        vzLcVs(['suspended' => 'yes']),
        vzLcVs(['vpsid' => '71']),
        ['vs' => []],
        Http::response('', 500),
    ]]);

    $active = vzLcProvisioner()->syncStatus(vzLcInfo($instance));
    expect($active->status)->toBe('active')
        ->and($active->externalRef)->toBe('70')
        ->and($active->meta['provider_mapping'])->toBe(['provider_id' => '70', 'user_id' => 21, 'plan_id' => '3']);

    expect(vzLcProvisioner()->syncStatus(vzLcInfo($instance))->status)->toBe('suspended')
        ->and(vzLcClaim($instance)->state)->toBe('suspended')
        ->and(vzLcProvisioner()->syncStatus(vzLcInfo($instance))->status)->toBe('active')
        ->and(vzLcClaim($instance)->only(['state', 'remote_name']))->toBe(['state' => 'active', 'remote_name' => 'renamed.example.test']);

    foreach (range(1, 5) as $ignored) {
        vzLcRefused(fn () => vzLcProvisioner()->syncStatus(vzLcInfo($instance)));
    }

    expect($log->count())->toBe(8)
        ->and($log[0]['query'])->toMatchArray(['act' => 'vs', 'vpsid' => '70', 'search' => 'Search'])
        ->and(vzLcClaim($instance)->state)->toBe('active');
});

test('virtualizor sync of an unverified claim refuses without network and reports absence otherwise', function () {
    $unknown = vzLcInstance();
    $script = vzLcCreateScript();
    $script['POST addvs'] = [fn () => Http::failedConnection()];
    vzLcFake($script);
    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($unknown)));

    $log = vzLcFake([]);
    vzLcRefused(fn () => vzLcProvisioner()->syncStatus(vzLcInfo($unknown)));
    $absent = vzLcProvisioner()->syncStatus(vzLcInfo(vzLcInstance()));

    expect($log->count())->toBe(0)
        ->and($absent->meta['provider_reconciliation'])->toBe('absent');
});

test('virtualizor lifecycle is refused without network when the endpoint is re-pointed', function () {
    [$instance] = vzLcActive();
    vzLcPointAt('https://other.example.test:4085');
    $log = vzLcFake([]);

    foreach (['provision', 'suspend', 'unsuspend', 'terminate', 'syncStatus'] as $operation) {
        $exception = vzLcRefused(fn () => vzLcProvisioner()->{$operation}(vzLcInfo($instance)));
        expect($exception->errors()['instance'][0])->toBe(__('virtualizor::messages.errors.endpoint_changed'));
    }
    vzLcRefused(fn () => vzLcProvisioner()->changePlan(vzLcInfo($instance), ['provider_settings' => ['plan_id' => '4']]));

    expect($log->count())->toBe(0)
        ->and(vzLcClaim($instance)->state)->toBe('active');
});

test('virtualizor hand written provider mapping without a claim row is refused without network', function () {
    $instance = vzLcInstance(meta: ['provider_mapping' => ['provider_id' => '70']], externalRef: '70');
    $log = vzLcFake(vzLcCreateScript());

    foreach (['provision', 'suspend', 'unsuspend', 'terminate', 'syncStatus'] as $operation) {
        vzLcRefused(fn () => vzLcProvisioner()->{$operation}(vzLcInfo($instance)));
    }

    expect($log->count())->toBe(0)
        ->and(vzLcClaim($instance))->toBeNull();
});

test('virtualizor stale revision between read and write never overwrites the row', function () {
    [$instance, $claim] = vzLcActive();
    vzLcFake(['GET vs:suspend' => [function () use ($claim) {
        DB::table('virtualizor_accounts')->where('id', $claim->id)->update(['revision' => 9]);

        return Http::response(['done' => 1], 200);
    }]]);

    vzLcRefused(fn () => vzLcProvisioner()->suspend(vzLcInfo($instance)));
    expect(vzLcClaim($instance)->only(['state', 'revision']))->toBe(['state' => 'active', 'revision' => 9]);

    $fresh = vzLcInstance();
    $script = vzLcCreateScript(71);
    $script['POST addvs'] = [function () use ($fresh) {
        DB::table('virtualizor_accounts')->where('service_instance_id', $fresh->id)->update(['state' => 'failed', 'revision' => 5]);

        return Http::response(vzLcCreated(71), 200);
    }];
    vzLcFake($script);

    vzLcRefused(fn () => vzLcProvisioner()->provision(vzLcInfo($fresh)));
    expect(vzLcClaim($fresh)->only(['state', 'revision', 'remote_id']))->toBe(['state' => 'failed', 'revision' => 5, 'remote_id' => 'pending:'.$fresh->id]);
});

test('virtualizor never leaks the api key, api password or generated passwords', function () {
    $instance = vzLcInstance();
    $log = vzLcFake(vzLcCreateScript());
    vzLcProvisioner()->provision(vzLcInfo($instance));
    $passwords = [$log[2]['body']['rootpass'], $log[2]['body']['user_pass']];

    vzLcFake(['GET vs:vpsid' => [vzLcVs(), Http::response(['error' => [VZ_LC_KEY]], 500), fn () => Http::failedConnection()]]);
    $info = vzLcProvisioner()->syncStatus(vzLcInfo($instance));
    $exceptions = [
        vzLcRefused(fn () => vzLcProvisioner()->syncStatus(vzLcInfo($instance))),
        vzLcRefused(fn () => vzLcProvisioner()->syncStatus(vzLcInfo($instance))),
    ];
    $panel = vzLcProvisioner()->panel(vzLcInfo($instance));
    $output = json_encode([
        $info->meta, $info->providerSettings, $info->externalRef, $panel,
        array_map(static fn (ValidationException $exception): array => [$exception->errors(), $exception->getMessage()], $exceptions),
        VirtualizorAccount::query()->get()->toArray(), $instance->fresh()->getAttributes(),
    ]);

    foreach ([VZ_LC_KEY, VZ_LC_PASS, ...$passwords] as $secret) {
        expect($output)->not->toContain($secret);
    }
});

test('virtualizor panel shows the claimed vps without network or power actions', function () {
    [$instance] = vzLcActive();
    $log = vzLcFake([]);

    $panel = vzLcProvisioner()->panel(vzLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and(collect($panel?->fields)->pluck('value')->all())->toContain('70', '3', 'vps70.example.test')
        ->and(vzLcProvisioner()->actions(vzLcInfo($instance)))->toBe([])
        ->and(vzLcProvisioner()->panel(vzLcInfo(vzLcInstance())))->toBeNull();
});

test('virtualizor health lists plans read-only and fails closed', function () {
    $settings = ['api_url' => VZ_LC_URL, 'api_token' => VZ_LC_KEY, 'api_secret' => VZ_LC_PASS];
    $log = vzLcFake(['POST plans' => [vzLcPlans(), ['error' => ['Invalid API credentials']], Http::response('<html></html>', 200), Http::response('', 401)]]);

    expect(vzLcProvisioner()->testServer($settings)->ok)->toBeTrue()
        ->and(vzLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(vzLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(vzLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(vzLcProvisioner()->testServer(['api_url' => VZ_LC_URL, 'api_token' => VZ_LC_KEY])->ok)->toBeFalse()
        ->and(vzLcKeys($log))->toBe(['POST plans', 'POST plans', 'POST plans', 'POST plans']);
});
