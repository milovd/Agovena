<?php

declare(strict_types=1);

use Agovena\Extensions\Enhance\EnhanceAccount;
use Agovena\Extensions\Enhance\EnhanceEndpoint;
use Agovena\Extensions\Enhance\EnhanceProvisioner;
use Agovena\Modules\Provisioning\EloquentProvisionedServiceResolver;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ServiceInstanceRuntimeSecretStore;
use Agovena\Modules\Provisioning\Support\ServerProviderException;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

const EN_LC_TOKEN = 'enhance-token-SECRET-4f1c2a';
const EN_LC_URL = 'https://cp.example.test';
const EN_LC_ENDPOINT = 'https://cp.example.test:443';
const EN_LC_ACCOUNT = '0d4e5f6a-1b2c-4d3e-8f90-a1b2c3d4e5f6';
const EN_LC_ORG = '7a8b9c0d-1e2f-4a3b-9c4d-5e6f7a8b9c0d';
const EN_LC_WEBSITE = 'c1d2e3f4-a5b6-4c7d-8e9f-0a1b2c3d4e5f';

beforeEach(function (): void {
    installAndEnableModule('provisioning');
    app(ExtensionManager::class)->discover();
    installAndEnableExtension('enhance');
    app()->forgetInstance(EnhanceProvisioner::class);
    enLcPointAt(EN_LC_URL);
    enLcRegisterFake();
});

function enLcPointAt(string $url): void
{
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('enhance', 'api_url', $url);
    $settings->set('enhance', 'api_token', EN_LC_TOKEN, secret: true);
    $settings->set('enhance', 'account_id', EN_LC_ACCOUNT);
    $settings->set('enhance', 'verify_tls', true);
    $settings->set('enhance', 'timeout', '20');
}

function enLcProvisioner(): EnhanceProvisioner
{
    return app(EnhanceProvisioner::class);
}

/** @param array<string, mixed>|null $settings */
function enLcInstance(?array $settings = null, array $meta = [], ?string $externalRef = null): ServiceInstance
{
    static $sequence = 0;
    $sequence++;
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-ENLC-'.$sequence.'-'.bin2hex(random_bytes(3)),
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'enhance',
        'customer_email' => 'buyer'.$sequence.'@example.test',
        'customer_name' => 'Buyer '.$sequence,
        'external_ref' => $externalRef,
        'meta' => array_merge(['label' => 'Hosting'], $meta),
    ]);
    app(ServiceInstanceRuntimeSecretStore::class)->put($instance->id, null, $settings ?? ['plan' => '7', 'domain' => 'Site'.$sequence.'.Example.org']);

    return $instance->fresh();
}

function enLcInfo(ServiceInstance $instance): ServiceInstanceInfo
{
    return EloquentProvisionedServiceResolver::info($instance->fresh() ?? $instance);
}

function enLcDomain(ServiceInstance $instance): string
{
    return strtolower((string) (app(ServiceInstanceRuntimeSecretStore::class)->get($instance->id)['provider_settings']['domain'] ?? ''));
}

function enLcAcc(string $suffix = ''): string
{
    return '/api/orgs/'.EN_LC_ACCOUNT.$suffix;
}

function enLcOrg(string $suffix = ''): string
{
    return '/api/orgs/'.EN_LC_ORG.$suffix;
}

/** @return array<string, mixed> */
function enLcPlans(int ...$ids): array
{
    return [
        'items' => array_map(static fn (int $id): array => ['id' => $id, 'name' => 'Plan '.$id, 'orgId' => EN_LC_ACCOUNT, 'subscriptionsCount' => 0, 'planType' => 'shared'], $ids),
        'total' => count($ids),
    ];
}

/** @param array<string, mixed> $overrides @return array<string, mixed> */
function enLcSubscription(array $overrides = []): array
{
    return array_merge([
        'id' => 501,
        'planId' => 7,
        'planName' => 'Plan 7',
        'subscriberId' => EN_LC_ORG,
        'vendorId' => EN_LC_ACCOUNT,
        'status' => 'active',
        'resources' => [],
        'allowances' => [],
        'selections' => [],
        'planType' => 'shared',
        'allowedPhpVersions' => ['php83'],
        'defaultPhpVersion' => 'php83',
        'redisAllowed' => false,
        'friendlyName' => '',
        'persistentAppsAllowed' => false,
    ], $overrides);
}

/** @param list<array<string, mixed>> $items @return array<string, mixed> */
function enLcList(array $items): array
{
    return ['items' => $items, 'total' => count($items)];
}

function enLcCreated(int|string $id): PromiseInterface
{
    return Http::response(['id' => $id], 201);
}

function enLcNoContent(): PromiseInterface
{
    return Http::response('', 204);
}

function enLcState(): stdClass
{
    static $state = null;

    return $state ??= new stdClass;
}

function enLcRegisterFake(): void
{
    enLcFake([]);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $state = enLcState();
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
 * Replaces the Enhance script. Each "METHOD /path" maps to a queue of responses;
 * arrays are 200 JSON bodies. Unscripted calls answer 599.
 *
 * @param  array<string, list<mixed>>  $script
 */
function enLcFake(array $script): ArrayObject
{
    $state = enLcState();
    $state->script = $script;
    $state->log = new ArrayObject;

    return $state->log;
}

/** @return list<string> */
function enLcKeys(ArrayObject $log): array
{
    return array_values(array_map(static fn (array $entry): string => $entry['key'], $log->getArrayCopy()));
}

/** @return array<string, list<mixed>> */
function enLcCreateScript(): array
{
    return [
        'GET '.enLcAcc('/plans') => [enLcPlans(7, 8)],
        'POST '.enLcAcc('/customers') => [enLcCreated(EN_LC_ORG)],
        'POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions') => [enLcCreated(501)],
        'POST '.enLcOrg('/websites') => [enLcCreated(EN_LC_WEBSITE)],
    ];
}

function enLcClaim(ServiceInstance $instance): ?EnhanceAccount
{
    return EnhanceAccount::query()->where('service_instance_id', $instance->id)->first();
}

/** @return array{0: ServiceInstance, 1: EnhanceAccount} */
function enLcActive(): array
{
    $instance = enLcInstance();
    enLcFake(enLcCreateScript());
    enLcProvisioner()->provision(enLcInfo($instance));

    return [$instance, enLcClaim($instance)];
}

function enLcRefused(callable $operation): ValidationException
{
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('instance');

        return $exception;
    }

    throw new RuntimeException('Expected the Enhance operation to be refused.');
}

function enLcMessage(ValidationException $exception): string
{
    return $exception->errors()['instance'][0];
}

test('enhance migration creates the claim table with unique identity columns and is reversible', function () {
    expect(Schema::hasColumns('enhance_accounts', [
        'id', 'service_instance_id', 'endpoint', 'remote_id', 'remote_name', 'customer_org_id', 'website_id', 'plan', 'state', 'revision', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $row = ['endpoint' => EN_LC_ENDPOINT, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()];
    DB::table('enhance_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => '501', 'remote_name' => 'a.example.org']);

    expect(fn () => DB::table('enhance_accounts')->insert($row + ['service_instance_id' => 900002, 'remote_id' => '501', 'remote_name' => 'b.example.org']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('enhance_accounts')->insert($row + ['service_instance_id' => 900003, 'remote_id' => '502', 'remote_name' => 'a.example.org']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('enhance_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => '503', 'remote_name' => 'c.example.org']))
        ->toThrow(QueryException::class)
        ->and(DB::table('enhance_accounts')->where('service_instance_id', 900001)->value('revision'))->toBe(0);

    DB::table('enhance_accounts')->insert(['endpoint' => 'https://other.example.test:443'] + $row + ['service_instance_id' => 900004, 'remote_id' => '501', 'remote_name' => 'a.example.org']);
    expect(DB::table('enhance_accounts')->count())->toBe(2);

    $migration = require config('agovena.packages.optional_packages_path').'/extensions/provisioning/enhance/database/migrations/2026_10_05_000000_create_enhance_accounts_table.php';
    $migration->up();
    $migration->down();
    expect(Schema::hasTable('enhance_accounts'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('enhance_accounts'))->toBeTrue();
});

test('enhance translations exist in english and dutch for every key', function () {
    $path = config('agovena.packages.optional_packages_path').'/extensions/provisioning/enhance/lang/';
    $en = Arr::dot(require $path.'en/messages.php');
    $nl = Arr::dot(require $path.'nl/messages.php');

    expect(array_keys($nl))->toBe(array_keys($en))
        ->and($en)->toHaveKeys(['errors.claimed', 'errors.conflict', 'errors.unverified', 'errors.endpoint_changed', 'errors.plan_unavailable', 'errors.demo_disabled', 'errors.invalid_mapping', 'errors.terminated', 'panel.subscription_id'])
        ->and(__('enhance::messages.errors.claimed'))->toBe($en['errors.claimed']);
});

test('enhance endpoint identity is normalized and rejects paths and credentials', function () {
    expect(EnhanceEndpoint::normalize('HTTPS://CP.Example.TEST/'))->toBe(EN_LC_ENDPOINT)
        ->and(EnhanceEndpoint::normalize('http://10.0.0.5:8080'))->toBe('http://10.0.0.5:8080');

    foreach (['https://cp.example.test/api', 'https://user:pass@cp.example.test', 'ftp://cp.example.test', 'https://cp.example.test?x=1', ''] as $url) {
        expect(fn () => EnhanceEndpoint::normalize($url))->toThrow(ServerProviderException::class);
    }
});

test('enhance create claims first, sends the documented calls once and a retry is idempotent', function () {
    $instance = enLcInstance();
    $log = enLcFake(enLcCreateScript());

    enLcProvisioner()->provision(enLcInfo($instance));

    expect(enLcKeys($log))->toBe([
        'GET '.enLcAcc('/plans'),
        'POST '.enLcAcc('/customers'),
        'POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions'),
        'POST '.enLcOrg('/websites'),
    ])
        ->and(array_values(array_unique(array_column($log->getArrayCopy(), 'authorization'))))->toBe(['Bearer '.EN_LC_TOKEN])
        ->and(array_values(array_unique(array_column($log->getArrayCopy(), 'host'))))->toBe([EN_LC_URL])
        ->and($log[1]['body'])->toBe(['name' => $instance->customer_name])
        ->and($log[2]['body'])->toBe(['planId' => 7])
        ->and($log[3]['body'])->toBe(['domain' => enLcDomain($instance), 'subscriptionId' => 501]);

    $claim = enLcClaim($instance);
    expect($claim->only(['endpoint', 'remote_id', 'remote_name', 'customer_org_id', 'website_id', 'plan', 'state']))->toBe([
        'endpoint' => EN_LC_ENDPOINT,
        'remote_id' => '501',
        'remote_name' => enLcDomain($instance),
        'customer_org_id' => EN_LC_ORG,
        'website_id' => EN_LC_WEBSITE,
        'plan' => '7',
        'state' => 'active',
    ]);

    $log = enLcFake([]);
    enLcProvisioner()->provision(enLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and(enLcClaim($instance)->revision)->toBe($claim->revision);
});

test('enhance orchestrated provisioning activates the service without persisting secrets', function () {
    $instance = enLcInstance();
    $log = enLcFake(enLcCreateScript() + ['GET '.enLcOrg('/subscriptions/501') => [enLcSubscription()]]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->status)->toBe(ServiceInstanceStatus::Active)
        ->and($result->external_ref)->toBe('501')
        ->and(enLcKeys($log))->toContain('GET '.enLcOrg('/subscriptions/501'));
    expect(json_encode([$result->fresh()->getAttributes(), enLcClaim($instance)->getAttributes()]))->not->toContain(EN_LC_TOKEN);
});

test('enhance refuses a second service claiming the same domain without network', function () {
    [$first] = enLcActive();
    $second = enLcInstance(['plan' => '7', 'domain' => strtoupper(enLcDomain($first))]);
    $log = enLcFake(enLcCreateScript());

    $exception = enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($second)));

    expect(enLcMessage($exception))->toBe(__('enhance::messages.errors.claimed'))
        ->and($log->count())->toBe(0)
        ->and(enLcClaim($second))->toBeNull();
});

test('enhance customer create timeout is never retried and goes to manual review', function () {
    $instance = enLcInstance();
    $script = enLcCreateScript();
    $script['POST '.enLcAcc('/customers')] = [fn () => Http::failedConnection()];
    enLcFake($script);

    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));
    expect(enLcClaim($instance)->only(['state', 'customer_org_id', 'remote_id']))->toBe(['state' => 'unknown', 'customer_org_id' => null, 'remote_id' => 'pending:'.$instance->id]);

    $log = enLcFake(enLcCreateScript());
    $exception = enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));

    expect(enLcMessage($exception))->toBe(__('enhance::messages.errors.unverified'))
        ->and($log->count())->toBe(0)
        ->and(enLcClaim($instance)->state)->toBe('unknown');
});

test('enhance subscription create timeout reconciles only inside the customer org agovena created', function () {
    $instance = enLcInstance();
    $script = enLcCreateScript();
    $script['POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions')] = [fn () => Http::failedConnection()];
    enLcFake($script);

    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));
    expect(enLcClaim($instance)->only(['state', 'customer_org_id', 'remote_id']))->toBe(['state' => 'unknown', 'customer_org_id' => EN_LC_ORG, 'remote_id' => 'pending:'.$instance->id]);

    $log = enLcFake([
        'GET '.enLcAcc('/plans') => [enLcPlans(7)],
        'GET '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions') => [enLcList([enLcSubscription()])],
    ]);
    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));
    expect(enLcKeys($log))->not->toContain('POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions'))
        ->and(enLcClaim($instance)->state)->toBe('unknown');

    $log = enLcFake([
        'GET '.enLcAcc('/plans') => [enLcPlans(7)],
        'GET '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions') => [enLcList([])],
        'POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions') => [enLcCreated(501)],
        'POST '.enLcOrg('/websites') => [enLcCreated(EN_LC_WEBSITE)],
    ]);
    enLcProvisioner()->provision(enLcInfo($instance));

    expect(enLcKeys($log))->toBe([
        'GET '.enLcAcc('/plans'),
        'GET '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions'),
        'POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions'),
        'POST '.enLcOrg('/websites'),
    ])
        ->and(enLcKeys($log))->not->toContain('POST '.enLcAcc('/customers'))
        ->and(enLcClaim($instance)->only(['state', 'remote_id']))->toBe(['state' => 'active', 'remote_id' => '501']);
});

test('enhance website create timeout reconciles by the stored subscription only', function () {
    $instance = enLcInstance();
    $script = enLcCreateScript();
    $script['POST '.enLcOrg('/websites')] = [fn () => Http::failedConnection()];
    enLcFake($script);

    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));
    expect(enLcClaim($instance)->only(['state', 'remote_id', 'website_id']))->toBe(['state' => 'unknown', 'remote_id' => '501', 'website_id' => null]);

    $website = ['id' => EN_LC_WEBSITE, 'domain' => ['domain' => enLcDomain($instance)], 'status' => 'active', 'orgId' => EN_LC_ORG];
    $log = enLcFake(['GET '.enLcOrg('/websites') => [enLcList([$website])]]);
    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));
    expect(enLcKeys($log))->toBe(['GET '.enLcOrg('/websites')])
        ->and($log[0]['query'])->toBe(['subscriptionId' => '501'])
        ->and(enLcClaim($instance)->state)->toBe('unknown');

    $log = enLcFake([
        'GET '.enLcOrg('/websites') => [enLcList([])],
        'POST '.enLcOrg('/websites') => [enLcCreated(EN_LC_WEBSITE)],
    ]);
    enLcProvisioner()->provision(enLcInfo($instance));

    expect(enLcKeys($log))->toBe(['GET '.enLcOrg('/websites'), 'POST '.enLcOrg('/websites')])
        ->and(enLcClaim($instance)->only(['state', 'website_id']))->toBe(['state' => 'active', 'website_id' => EN_LC_WEBSITE]);
});

test('enhance orchestrator moves an ambiguous create to manual review', function () {
    $instance = enLcInstance();
    $script = enLcCreateScript();
    $script['POST '.enLcAcc('/customers')] = [fn () => Http::failedConnection()];
    enLcFake($script);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and(enLcClaim($instance)->state)->toBe('unknown');
});

test('enhance unexpected success bodies are never marked active', function (string $step, mixed $response) {
    $instance = enLcInstance();
    $script = enLcCreateScript();
    $script[$step] = [$response];
    enLcFake($script);

    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));

    expect(enLcClaim($instance)->state)->toBe('unknown');
})->with([
    'customer without id' => ['POST /api/orgs/'.EN_LC_ACCOUNT.'/customers', ['error' => 'nope']],
    'customer id not a uuid' => ['POST /api/orgs/'.EN_LC_ACCOUNT.'/customers', ['id' => '../x']],
    'subscription id not an integer' => ['POST /api/orgs/'.EN_LC_ACCOUNT.'/customers/'.EN_LC_ORG.'/subscriptions', ['id' => '501']],
    'website without id' => ['POST /api/orgs/'.EN_LC_ORG.'/websites', []],
]);

test('enhance documented rejections fail the claim and a retry creates without duplicates', function () {
    $instance = enLcInstance();
    $script = enLcCreateScript();
    $script['POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions')] = [Http::response('', 409)];
    enLcFake($script);

    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));
    expect(enLcClaim($instance)->only(['state', 'customer_org_id']))->toBe(['state' => 'failed', 'customer_org_id' => EN_LC_ORG]);

    $script = enLcCreateScript();
    unset($script['POST '.enLcAcc('/customers')]);
    $log = enLcFake($script);
    enLcProvisioner()->provision(enLcInfo($instance));

    expect(enLcKeys($log))->toBe([
        'GET '.enLcAcc('/plans'),
        'POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions'),
        'POST '.enLcOrg('/websites'),
    ])->and(enLcClaim($instance)->state)->toBe('active');
});

test('enhance refuses a plan that the reseller org does not offer before creating anything', function () {
    $instance = enLcInstance();
    $log = enLcFake(['GET '.enLcAcc('/plans') => [['items' => [['id' => 8, 'name' => 'Plan 8']], 'total' => 2], ['items' => [['id' => 9, 'name' => 'Plan 9']], 'total' => 2]]]);

    $exception = enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));

    expect(enLcMessage($exception))->toBe(__('enhance::messages.errors.plan_unavailable'))
        ->and(enLcKeys($log))->toBe(['GET '.enLcAcc('/plans'), 'GET '.enLcAcc('/plans')])
        ->and($log[1]['query'])->toBe(['offset' => '1'])
        ->and(enLcClaim($instance)->state)->toBe('failed');
});

test('enhance refuses invalid product or connection settings before claiming', function (array $settings, array $connection) {
    $instance = enLcInstance($settings);
    foreach ($connection as $key => $value) {
        app(ExtensionSettingsRepository::class)->set('enhance', $key, $value);
    }
    $log = enLcFake(enLcCreateScript());

    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));

    expect($log->count())->toBe(0)
        ->and(enLcClaim($instance))->toBeNull();
})->with([
    'missing plan' => [['domain' => 'a.example.org'], []],
    'non numeric plan' => [['plan' => 'starter', 'domain' => 'a.example.org'], []],
    'missing domain' => [['plan' => '7'], []],
    'invalid domain' => [['plan' => '7', 'domain' => 'not a domain'], []],
    'account id not a uuid' => [['plan' => '7', 'domain' => 'a.example.org'], ['account_id' => 'master']],
]);

test('enhance refuses every provider call in the demo environment', function () {
    [$instance] = enLcActive();
    $fresh = enLcInstance();
    app()['env'] = 'demo';
    $log = enLcFake([]);

    try {
        enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($fresh)));
        enLcRefused(fn () => enLcProvisioner()->suspend(enLcInfo($instance)));
        enLcRefused(fn () => enLcProvisioner()->syncStatus(enLcInfo($instance)));
        $health = enLcProvisioner()->testServer(['api_url' => EN_LC_URL, 'api_token' => EN_LC_TOKEN, 'account_id' => EN_LC_ACCOUNT]);
    } finally {
        app()['env'] = 'testing';
    }

    expect($log->count())->toBe(0)
        ->and($health->ok)->toBeFalse()
        ->and(enLcClaim($fresh)->state)->toBe('failed')
        ->and(enLcClaim($instance)->state)->toBe('active');
});

test('enhance suspend and unsuspend patch the documented isSuspended flag and track state', function () {
    [$instance] = enLcActive();
    $log = enLcFake(['PATCH '.enLcOrg('/subscriptions/501') => [enLcNoContent(), enLcNoContent()]]);

    enLcProvisioner()->suspend(enLcInfo($instance));
    enLcProvisioner()->suspend(enLcInfo($instance));
    expect(enLcClaim($instance)->state)->toBe('suspended');

    enLcProvisioner()->unsuspend(enLcInfo($instance));

    expect(enLcKeys($log))->toBe(['PATCH '.enLcOrg('/subscriptions/501'), 'PATCH '.enLcOrg('/subscriptions/501')])
        ->and($log[0]['body'])->toBe(['isSuspended' => true])
        ->and($log[1]['body'])->toBe(['isSuspended' => false])
        ->and(enLcClaim($instance)->state)->toBe('active');
});

test('enhance suspend and unsuspend failures keep the stored state', function (mixed $response) {
    [$instance] = enLcActive();
    enLcFake(['PATCH '.enLcOrg('/subscriptions/501') => [$response]]);

    enLcRefused(fn () => enLcProvisioner()->suspend(enLcInfo($instance)));

    expect(enLcClaim($instance)->state)->toBe('active');
})->with([
    'server error' => [fn () => Http::response('', 500)],
    'not found' => [fn () => Http::response('', 404)],
    'unexpected body' => [['isSuspended' => false]],
]);

test('enhance terminate deletes the owned subscription and the customer org agovena created', function () {
    [$instance] = enLcActive();
    $log = enLcFake([
        'DELETE '.enLcOrg('/subscriptions/501') => [enLcNoContent()],
        'GET '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions') => [enLcList([enLcSubscription(['status' => 'deleted'])])],
        'GET '.enLcOrg('/websites') => [enLcList([['id' => EN_LC_WEBSITE, 'status' => 'deleted']])],
        'DELETE '.enLcOrg() => [enLcNoContent()],
    ]);

    enLcProvisioner()->terminate(enLcInfo($instance));

    expect(enLcKeys($log))->toBe([
        'DELETE '.enLcOrg('/subscriptions/501'),
        'GET '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions'),
        'GET '.enLcOrg('/websites'),
        'DELETE '.enLcOrg(),
    ])
        ->and($log[2]['query'])->toBe([])
        ->and(enLcClaim($instance)->only(['state', 'remote_id', 'customer_org_id']))->toBe(['state' => 'terminated', 'remote_id' => '501', 'customer_org_id' => null]);

    $log = enLcFake([]);
    enLcProvisioner()->terminate(enLcInfo($instance));
    expect($log->count())->toBe(0);
});

test('enhance terminate keeps a customer org that holds anything else', function (array $script) {
    [$instance] = enLcActive();
    $log = enLcFake(['DELETE '.enLcOrg('/subscriptions/501') => [enLcNoContent()]] + $script);

    enLcProvisioner()->terminate(enLcInfo($instance));

    expect(enLcKeys($log))->not->toContain('DELETE '.enLcOrg())
        ->and(enLcClaim($instance)->only(['state', 'customer_org_id']))->toBe(['state' => 'terminated', 'customer_org_id' => null]);
})->with([
    'another active subscription' => [[
        'GET /api/orgs/'.EN_LC_ACCOUNT.'/customers/'.EN_LC_ORG.'/subscriptions' => [['items' => [['id' => 501, 'status' => 'deleted'], ['id' => 777, 'status' => 'active']], 'total' => 2]],
    ]],
    'another website' => [[
        'GET /api/orgs/'.EN_LC_ACCOUNT.'/customers/'.EN_LC_ORG.'/subscriptions' => [['items' => [['id' => 501, 'status' => 'deleted']], 'total' => 1]],
        'GET /api/orgs/'.EN_LC_ORG.'/websites' => [['items' => [['id' => 'f00dfeed-0000-4000-8000-000000000001', 'status' => 'active']], 'total' => 1]],
    ]],
]);

test('enhance terminate failures keep the claim and never delete the customer org blindly', function () {
    [$instance] = enLcActive();
    $log = enLcFake(['DELETE '.enLcOrg('/subscriptions/501') => [Http::response('', 500)]]);

    enLcRefused(fn () => enLcProvisioner()->terminate(enLcInfo($instance)));

    expect(enLcKeys($log))->toBe(['DELETE '.enLcOrg('/subscriptions/501')])
        ->and(enLcClaim($instance)->only(['state', 'customer_org_id']))->toBe(['state' => 'active', 'customer_org_id' => EN_LC_ORG]);

    $log = enLcFake([
        'DELETE '.enLcOrg('/subscriptions/501') => [enLcNoContent()],
        'GET '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions') => [fn () => Http::failedConnection()],
    ]);
    enLcRefused(fn () => enLcProvisioner()->terminate(enLcInfo($instance)));

    expect(enLcKeys($log))->not->toContain('DELETE '.enLcOrg())
        ->and(enLcClaim($instance)->only(['state', 'customer_org_id']))->toBe(['state' => 'terminated', 'customer_org_id' => EN_LC_ORG]);
});

test('enhance terminate refuses unverified claims and closes an unclaimed service without network', function () {
    $instance = enLcInstance();
    $script = enLcCreateScript();
    $script['POST '.enLcAcc('/customers')] = [fn () => Http::failedConnection()];
    enLcFake($script);
    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));

    $log = enLcFake([]);
    enLcRefused(fn () => enLcProvisioner()->terminate(enLcInfo($instance)));
    enLcProvisioner()->terminate(enLcInfo(enLcInstance()));

    expect($log->count())->toBe(0)
        ->and(enLcClaim($instance)->state)->toBe('unknown');
});

test('enhance change plan patches the subscription to an offered plan and verifies it afterwards', function () {
    [$instance] = enLcActive();
    $log = enLcFake([
        'GET '.enLcAcc('/plans') => [enLcPlans(7, 8)],
        'PATCH '.enLcOrg('/subscriptions/501') => [enLcNoContent()],
        'GET '.enLcOrg('/subscriptions/501') => [enLcSubscription(['planId' => 8, 'planName' => 'Plan 8'])],
    ]);

    enLcProvisioner()->changePlan(enLcInfo($instance), ['id' => '2', 'provider_settings' => ['plan' => '8', 'domain' => 'ignored.example.org']]);

    expect(enLcKeys($log))->toBe(['GET '.enLcAcc('/plans'), 'PATCH '.enLcOrg('/subscriptions/501'), 'GET '.enLcOrg('/subscriptions/501')])
        ->and($log[1]['body'])->toBe(['planId' => 8])
        ->and(enLcClaim($instance)->only(['plan', 'state', 'remote_name']))->toBe(['plan' => '8', 'state' => 'active', 'remote_name' => enLcDomain($instance)]);
});

test('enhance change plan failures keep the recorded plan', function (array $script, array|string $plan) {
    [$instance] = enLcActive();
    $log = enLcFake($script);

    enLcRefused(fn () => enLcProvisioner()->changePlan(enLcInfo($instance), $plan));

    expect(enLcKeys($log))->toBe(array_keys($script))
        ->and(enLcClaim($instance)->plan)->toBe('7');
})->with([
    'invalid target' => [[], ['provider_settings' => ['plan' => 'gold']]],
    'plan not offered' => [['GET /api/orgs/'.EN_LC_ACCOUNT.'/plans' => [['items' => [['id' => 7, 'name' => 'Plan 7']], 'total' => 1]]], ['provider_settings' => ['plan' => '8']]],
    'patch refused' => [[
        'GET /api/orgs/'.EN_LC_ACCOUNT.'/plans' => [['items' => [['id' => 8, 'name' => 'Plan 8']], 'total' => 1]],
        'PATCH /api/orgs/'.EN_LC_ORG.'/subscriptions/501' => [fn () => Http::response('', 400)],
    ], '8'],
    'not verified afterwards' => [[
        'GET /api/orgs/'.EN_LC_ACCOUNT.'/plans' => [['items' => [['id' => 8, 'name' => 'Plan 8']], 'total' => 1]],
        'PATCH /api/orgs/'.EN_LC_ORG.'/subscriptions/501' => [fn () => Http::response('', 204)],
        'GET /api/orgs/'.EN_LC_ORG.'/subscriptions/501' => [['id' => 501, 'planId' => 7, 'subscriberId' => EN_LC_ORG, 'vendorId' => EN_LC_ACCOUNT, 'status' => 'active']],
    ], '8'],
]);

test('enhance sync maps the documented subscription status and suspension', function () {
    [$instance] = enLcActive();
    enLcFake(['GET '.enLcOrg('/subscriptions/501') => [
        enLcSubscription(),
        enLcSubscription(['suspendedBy' => EN_LC_ACCOUNT, 'planId' => 8]),
    ]]);

    $info = enLcProvisioner()->syncStatus(enLcInfo($instance));
    expect($info->status)->toBe('active')
        ->and($info->externalRef)->toBe('501')
        ->and($info->meta['provider_mapping'])->toBe(['provider_id' => '501', 'customer_org_id' => EN_LC_ORG, 'website_id' => EN_LC_WEBSITE, 'domain' => enLcDomain($instance), 'plan' => '7']);

    $info = enLcProvisioner()->syncStatus(enLcInfo($instance));
    expect($info->status)->toBe('suspended')
        ->and(enLcClaim($instance)->only(['state', 'plan']))->toBe(['state' => 'suspended', 'plan' => '8']);
});

test('enhance sync refuses deleted, foreign, unknown or failed provider data', function (mixed $response) {
    [$instance, $claim] = enLcActive();
    enLcFake(['GET '.enLcOrg('/subscriptions/501') => [$response]]);

    enLcRefused(fn () => enLcProvisioner()->syncStatus(enLcInfo($instance)));

    expect(enLcClaim($instance)->only(['state', 'revision']))->toBe(['state' => 'active', 'revision' => $claim->revision]);
})->with([
    'deleted' => [enLcSubscription(['status' => 'deleted'])],
    'other subscriber' => [enLcSubscription(['subscriberId' => '11111111-2222-4333-8444-555555555555'])],
    'other vendor' => [enLcSubscription(['vendorId' => '11111111-2222-4333-8444-555555555555'])],
    'other id' => [enLcSubscription(['id' => 502])],
    'unknown status' => [enLcSubscription(['status' => 'paused'])],
    'missing plan' => [Arr::except(enLcSubscription(), ['planId'])],
    'not found' => [fn () => Http::response('', 404)],
    'server error' => [fn () => Http::response(['error' => EN_LC_TOKEN], 500)],
]);

test('enhance sync of an unverified claim refuses without network and reports absence otherwise', function () {
    $instance = enLcInstance();
    $script = enLcCreateScript();
    $script['POST '.enLcAcc('/customers')] = [fn () => Http::failedConnection()];
    enLcFake($script);
    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($instance)));

    $log = enLcFake([]);
    enLcRefused(fn () => enLcProvisioner()->syncStatus(enLcInfo($instance)));
    $absent = enLcProvisioner()->syncStatus(enLcInfo(enLcInstance()));

    expect($log->count())->toBe(0)
        ->and($absent->meta['provider_reconciliation'])->toBe('absent');
});

test('enhance lifecycle is refused without network when the endpoint is re-pointed', function () {
    [$instance] = enLcActive();
    enLcPointAt('https://other.example.test');
    $log = enLcFake([]);

    foreach (['provision', 'suspend', 'unsuspend', 'terminate', 'syncStatus'] as $operation) {
        $exception = enLcRefused(fn () => enLcProvisioner()->{$operation}(enLcInfo($instance)));
        expect(enLcMessage($exception))->toBe(__('enhance::messages.errors.endpoint_changed'));
    }
    enLcRefused(fn () => enLcProvisioner()->changePlan(enLcInfo($instance), '8'));

    expect($log->count())->toBe(0)
        ->and(enLcClaim($instance)->state)->toBe('active');
});

test('enhance hand written provider mapping without a claim row is refused without network', function () {
    $instance = enLcInstance(meta: ['provider_mapping' => ['provider_id' => '501']], externalRef: '501');
    $log = enLcFake(enLcCreateScript());

    foreach (['provision', 'suspend', 'unsuspend', 'terminate', 'syncStatus'] as $operation) {
        enLcRefused(fn () => enLcProvisioner()->{$operation}(enLcInfo($instance)));
    }
    enLcRefused(fn () => enLcProvisioner()->changePlan(enLcInfo($instance), '8'));

    expect($log->count())->toBe(0)
        ->and(enLcClaim($instance))->toBeNull();
});

test('enhance stale revision between read and write never overwrites the row', function () {
    [$instance, $claim] = enLcActive();
    enLcFake(['PATCH '.enLcOrg('/subscriptions/501') => [function () use ($claim) {
        DB::table('enhance_accounts')->where('id', $claim->id)->update(['revision' => 9]);

        return Http::response('', 204);
    }]]);

    $exception = enLcRefused(fn () => enLcProvisioner()->suspend(enLcInfo($instance)));
    expect(enLcMessage($exception))->toBe(__('enhance::messages.errors.conflict'))
        ->and(enLcClaim($instance)->only(['state', 'revision']))->toBe(['state' => 'active', 'revision' => 9]);

    $fresh = enLcInstance();
    $script = enLcCreateScript();
    $script['POST '.enLcAcc('/customers/'.EN_LC_ORG.'/subscriptions')] = [enLcCreated(502)];
    $script['POST '.enLcOrg('/websites')] = [function () use ($fresh) {
        DB::table('enhance_accounts')->where('service_instance_id', $fresh->id)->update(['state' => 'failed', 'revision' => 5]);

        return enLcCreated(EN_LC_WEBSITE);
    }];
    enLcFake($script);

    enLcRefused(fn () => enLcProvisioner()->provision(enLcInfo($fresh)));
    expect(enLcClaim($fresh)->only(['state', 'revision', 'website_id']))->toBe(['state' => 'failed', 'revision' => 5, 'website_id' => null]);
});

test('enhance never leaks the api token', function () {
    [$instance] = enLcActive();
    enLcFake(['GET '.enLcOrg('/subscriptions/501') => [enLcSubscription(), Http::response(['error' => EN_LC_TOKEN], 500)]]);

    $info = enLcProvisioner()->syncStatus(enLcInfo($instance));
    $exception = enLcRefused(fn () => enLcProvisioner()->syncStatus(enLcInfo($instance)));
    $panel = enLcProvisioner()->panel(enLcInfo($instance));
    $output = json_encode([
        $info->meta, $info->providerSettings, $info->externalRef, $exception->errors(), $exception->getMessage(),
        $panel, EnhanceAccount::query()->get()->toArray(), $instance->fresh()->meta,
    ]);

    expect($output)->not->toContain(EN_LC_TOKEN);
});

test('enhance panel shows the claimed subscription without network or actions', function () {
    [$instance] = enLcActive();
    $log = enLcFake([]);

    $panel = enLcProvisioner()->panel(enLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and(collect($panel?->fields)->pluck('value')->all())->toContain('501', '7', enLcDomain($instance))
        ->and(enLcProvisioner()->actions(enLcInfo($instance)))->toBe([])
        ->and(enLcProvisioner()->panel(enLcInfo(enLcInstance())))->toBeNull();
});

test('enhance health reads the reseller org through the documented getOrg call and fails closed', function () {
    $settings = ['api_url' => EN_LC_URL, 'api_token' => EN_LC_TOKEN, 'account_id' => EN_LC_ACCOUNT];
    $org = ['id' => EN_LC_ACCOUNT, 'name' => 'Reseller', 'status' => 'active', 'subscriptionsCount' => 0, 'websitesCount' => 0, 'createdAt' => '2026-01-01', 'locale' => 'en'];
    $log = enLcFake(['GET '.enLcAcc() => [
        $org,
        array_merge($org, ['id' => EN_LC_ORG]),
        array_merge($org, ['status' => 'deleted']),
        Http::response('', 401),
    ]]);

    expect(enLcProvisioner()->testServer($settings)->ok)->toBeTrue()
        ->and(enLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(enLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(enLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(enLcProvisioner()->testServer(['api_url' => EN_LC_URL, 'api_token' => EN_LC_TOKEN])->ok)->toBeFalse()
        ->and(enLcKeys($log))->toBe(array_fill(0, 4, 'GET '.enLcAcc()))
        ->and($log[0]['authorization'])->toBe('Bearer '.EN_LC_TOKEN);
});
