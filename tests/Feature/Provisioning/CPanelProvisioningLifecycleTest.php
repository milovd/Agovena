<?php

declare(strict_types=1);

use Agovena\Extensions\CPanel\CPanelAccount;
use Agovena\Extensions\CPanel\CPanelEndpoint;
use Agovena\Extensions\CPanel\CPanelProvisioner;
use Agovena\Modules\Provisioning\EloquentProvisionedServiceResolver;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ServiceInstanceRuntimeSecretStore;
use Agovena\Modules\Provisioning\Support\ServerProviderException;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const CPANEL_LC_TOKEN = 'whm-token-SECRET-4f9a1c';
const CPANEL_LC_URL = 'https://whm.example.test:2087';
const CPANEL_LC_ENDPOINT = 'https://whm.example.test:2087';

beforeEach(function (): void {
    installAndEnableModule('provisioning');
    app(ExtensionManager::class)->discover();
    installAndEnableExtension('cpanel');
    app()->forgetInstance(CPanelProvisioner::class);
    cpanelLcPointAt(CPANEL_LC_URL);
    cpanelLcRegisterFake();
    Str::createRandomStringsUsing(static fn (int $length): string => str_repeat('K', $length));
});

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

function cpanelLcPointAt(string $url): void
{
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('cpanel', 'api_url', $url);
    $settings->set('cpanel', 'api_username', 'root');
    $settings->set('cpanel', 'api_token', CPANEL_LC_TOKEN, secret: true);
    $settings->set('cpanel', 'verify_tls', true);
    $settings->set('cpanel', 'timeout', '20');
}

function cpanelLcProvisioner(): CPanelProvisioner
{
    return app(CPanelProvisioner::class);
}

/** @param array<string, mixed> $meta */
function cpanelLcInstance(array $meta = [], string $package = 'starter'): ServiceInstance
{
    static $sequence = 0;
    $sequence++;
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-CPLC-'.$sequence.'-'.bin2hex(random_bytes(3)),
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'cpanel',
        'customer_email' => 'buyer'.$sequence.'@example.test',
        'meta' => array_merge(['label' => 'Hosting'], $meta),
    ]);
    app(ServiceInstanceRuntimeSecretStore::class)->put($instance->id, null, ['package' => $package]);

    return $instance->fresh();
}

function cpanelLcInfo(ServiceInstance $instance): ServiceInstanceInfo
{
    return EloquentProvisionedServiceResolver::info($instance->fresh());
}

/** The username Agovena generates while random strings are faked to a fixed letter. */
function cpanelLcUsername(int $instanceId, string $letter = 'k'): string
{
    return 'a'.str_repeat($letter, 7).base_convert((string) $instanceId, 10, 36);
}

/** @return array<string, mixed> */
function cpanelLcOk(string $function, array $data = []): array
{
    $payload = ['metadata' => ['command' => $function, 'result' => 1, 'reason' => 'OK', 'version' => 1]];
    if ($data !== []) {
        $payload['data'] = $data;
    }

    return $payload;
}

/** @return array<string, mixed> */
function cpanelLcFail(string $function, string $reason = 'Denied'): array
{
    return ['metadata' => ['command' => $function, 'result' => 0, 'reason' => $reason, 'version' => 1]];
}

/** @return array<string, mixed> */
function cpanelLcAccount(string $user, array $overrides = []): array
{
    return array_merge([
        'user' => $user,
        'domain' => $user.'.temp.example.test',
        'plan' => 'starter',
        'owner' => 'root',
        'suspended' => 0,
        'unix_startdate' => time(),
    ], $overrides);
}

/** @return array<string, mixed> listaccts exact-user search result */
function cpanelLcList(?string $user, array $overrides = []): array
{
    return cpanelLcOk('listaccts', ['acct' => $user === null ? [] : [cpanelLcAccount($user, $overrides)]]);
}

/** @return array<string, mixed> accountsummary result */
function cpanelLcSummary(string $user, array $overrides = []): array
{
    return cpanelLcOk('accountsummary', ['acct' => [cpanelLcAccount($user, $overrides)]]);
}

/** @return array<string, mixed> */
function cpanelLcPackages(string ...$names): array
{
    return cpanelLcOk('listpkgs', ['pkg' => array_map(static fn (string $name): array => ['name' => $name], $names)]);
}

/** Shared state of the scripted WHM fake that beforeEach registers once per test. */
function cpanelLcWhm(): stdClass
{
    static $state = null;

    return $state ??= new stdClass;
}

function cpanelLcRegisterFake(): void
{
    cpanelLcFake([]);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $whm = cpanelLcWhm();
        $parts = parse_url($request->url());
        $function = basename((string) ($parts['path'] ?? ''));
        $query = [];
        foreach (explode('&', (string) ($parts['query'] ?? '')) as $pair) {
            if ($pair !== '') {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                $query[urldecode($key)] = urldecode($value);
            }
        }
        $whm->log[] = [
            'function' => $function,
            'method' => $request->method(),
            'base' => ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').':'.($parts['port'] ?? '').($parts['path'] ?? ''),
            'query' => $query,
            'authorization' => $request->header('Authorization')[0] ?? null,
        ];
        if (($whm->script[$function] ?? []) === []) {
            return Http::response(['unexpected' => $function], 599);
        }
        $next = array_shift($whm->script[$function]);

        return is_callable($next) ? $next($request) : Http::response($next, 200);
    });
}

/**
 * Replaces the WHM script. Each function name maps to a queue of responses; the
 * returned log records every request with its parsed query for exact assertions.
 *
 * @param  array<string, list<mixed>>  $script
 */
function cpanelLcFake(array $script): ArrayObject
{
    $whm = cpanelLcWhm();
    $whm->script = $script;
    $whm->log = new ArrayObject;

    return $whm->log;
}

/** @return list<string> */
function cpanelLcFunctions(ArrayObject $log): array
{
    return array_values(array_map(static fn (array $entry): string => $entry['function'], $log->getArrayCopy()));
}

function cpanelLcClaim(ServiceInstance $instance): ?CPanelAccount
{
    return CPanelAccount::query()->where('service_instance_id', $instance->id)->first();
}

/**
 * Provision a fresh active account; callers install their own HTTP fake afterwards.
 *
 * @return array{0: ServiceInstance, 1: CPanelAccount}
 */
function cpanelLcActive(string $package = 'starter'): array
{
    $instance = cpanelLcInstance(package: $package);
    cpanelLcFake([
        'listpkgs' => [cpanelLcPackages($package, 'business')],
        'createacct' => [cpanelLcOk('createacct', ['package' => $package])],
    ]);
    cpanelLcProvisioner()->provision(cpanelLcInfo($instance));

    return [$instance, cpanelLcClaim($instance)];
}

function cpanelLcRefused(callable $operation): ValidationException
{
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('instance');

        return $exception;
    }

    throw new RuntimeException('Expected the cPanel operation to be refused.');
}

test('cpanel migration creates the claim table with unique identity columns and is reversible', function () {
    expect(Schema::hasTable('cpanel_accounts'))->toBeTrue()
        ->and(Schema::hasColumns('cpanel_accounts', [
            'id', 'service_instance_id', 'endpoint', 'remote_id', 'remote_name', 'plan', 'state', 'revision', 'created_at', 'updated_at',
        ]))->toBeTrue();

    $row = ['endpoint' => CPANEL_LC_ENDPOINT, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()];
    DB::table('cpanel_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => 'aduplicate1', 'remote_name' => 'shop.example.test']);

    expect(fn () => DB::table('cpanel_accounts')->insert($row + ['service_instance_id' => 900002, 'remote_id' => 'aduplicate1']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('cpanel_accounts')->insert($row + ['service_instance_id' => 900003, 'remote_id' => 'aother00001', 'remote_name' => 'shop.example.test']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('cpanel_accounts')->insert($row + ['service_instance_id' => 900001, 'remote_id' => 'aother00002']))
        ->toThrow(QueryException::class)
        ->and(DB::table('cpanel_accounts')->where('service_instance_id', 900001)->value('revision'))->toBe(0);

    DB::table('cpanel_accounts')->insert(['endpoint' => 'https://other.example.test:2087'] + $row + ['service_instance_id' => 900004, 'remote_id' => 'aduplicate1', 'remote_name' => 'shop.example.test']);
    expect(DB::table('cpanel_accounts')->count())->toBe(2);

    $migration = require config('agovena.packages.optional_packages_path').'/extensions/provisioning/cpanel/database/migrations/2026_10_05_000000_create_cpanel_accounts_table.php';
    $migration->up();
    expect(Schema::hasTable('cpanel_accounts'))->toBeTrue();
    $migration->down();
    expect(Schema::hasTable('cpanel_accounts'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('cpanel_accounts'))->toBeTrue();
});

test('cpanel endpoint identity is normalized and rejects paths and credentials', function () {
    expect(CPanelEndpoint::normalize('HTTPS://WHM.Example.TEST:2087/'))->toBe('https://whm.example.test:2087')
        ->and(CPanelEndpoint::normalize('https://whm.example.test'))->toBe('https://whm.example.test:443')
        ->and(CPanelEndpoint::normalize('http://whm.example.test'))->toBe('http://whm.example.test:80');

    foreach ([
        'https://whm.example.test:2087/json-api',
        'https://root:pw@whm.example.test:2087',
        'https://whm.example.test:2087?x=1',
        'ftp://whm.example.test',
        '',
    ] as $url) {
        expect(fn () => CPanelEndpoint::normalize($url))->toThrow(ServerProviderException::class);
    }
});

test('cpanel create sends the documented createacct call once and a retry is idempotent', function () {
    $instance = cpanelLcInstance();
    $username = cpanelLcUsername($instance->id);
    $log = cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter', 'business')],
        'createacct' => [cpanelLcOk('createacct', ['package' => 'starter'])],
        'accountsummary' => [cpanelLcSummary($username)],
    ]);

    cpanelLcProvisioner()->provision(cpanelLcInfo($instance));
    cpanelLcProvisioner()->provision(cpanelLcInfo($instance));

    expect(cpanelLcFunctions($log))->toBe(['listpkgs', 'createacct'])
        ->and($log[0]['method'])->toBe('GET')
        ->and($log[0]['base'])->toBe('https://whm.example.test:2087/json-api/listpkgs')
        ->and($log[0]['query'])->toBe(['api.version' => '1', 'want' => 'creatable'])
        ->and($log[0]['authorization'])->toBe('whm root:'.CPANEL_LC_TOKEN)
        ->and($log[1]['method'])->toBe('GET')
        ->and($log[1]['base'])->toBe('https://whm.example.test:2087/json-api/createacct')
        ->and($log[1]['query'])->toBe(['api.version' => '1', 'username' => $username, 'plan' => 'starter', 'showpass' => 'n'])
        ->and($log[1]['authorization'])->toBe('whm root:'.CPANEL_LC_TOKEN)
        ->and($username)->toMatch('/\Aa[a-z0-9]{8,15}\z/');

    $claim = cpanelLcClaim($instance);
    expect($claim->state)->toBe('active')
        ->and($claim->remote_id)->toBe($username)
        ->and($claim->endpoint)->toBe(CPANEL_LC_ENDPOINT)
        ->and($claim->plan)->toBe('starter')
        ->and($claim->revision)->toBe(1);

    $info = cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance));
    expect(cpanelLcFunctions($log))->toBe(['listpkgs', 'createacct', 'accountsummary'])
        ->and($log[2]['base'])->toBe('https://whm.example.test:2087/json-api/accountsummary')
        ->and($log[2]['query'])->toBe(['api.version' => '1', 'user' => $username])
        ->and($info->status)->toBe('active')
        ->and($info->externalRef)->toBe($username)
        ->and($info->providerKey)->toBe('cpanel')
        ->and($info->meta['provider_mapping'])->toBe(['provider_id' => $username, 'domain' => $username.'.temp.example.test', 'plan' => 'starter'])
        ->and($info->serverSettings)->toBeNull()
        ->and(cpanelLcClaim($instance)->remote_name)->toBe($username.'.temp.example.test');
});

test('cpanel orchestrated provisioning activates the service without persisting secrets', function () {
    $instance = cpanelLcInstance();
    $username = cpanelLcUsername($instance->id);
    cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [cpanelLcOk('createacct', ['package' => 'starter'])],
        'accountsummary' => [cpanelLcSummary($username)],
    ]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->status)->toBe(ServiceInstanceStatus::Active)
        ->and($result->external_ref)->toBe($username);
    $persisted = json_encode([$result->fresh()->getAttributes(), cpanelLcClaim($instance)->getAttributes()]);
    expect($persisted)->not->toContain(CPANEL_LC_TOKEN)
        ->and(strtolower((string) $persisted))->not->toContain('password');
});

test('cpanel refuses a second service claiming the same remote identifier without network', function () {
    $owner = cpanelLcInstance();
    $intruder = cpanelLcInstance();
    CPanelAccount::query()->create([
        'service_instance_id' => $owner->id,
        'endpoint' => CPANEL_LC_ENDPOINT,
        'remote_id' => cpanelLcUsername($intruder->id),
        'state' => 'active',
    ]);
    $log = cpanelLcFake([]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($intruder)));

    expect($log->count())->toBe(0)
        ->and(cpanelLcClaim($intruder))->toBeNull()
        ->and(CPanelAccount::query()->where('remote_id', cpanelLcUsername($intruder->id))->value('service_instance_id'))->toBe($owner->id);
});

test('cpanel create timeout is never blindly retried and reconciles by the stored username', function () {
    $instance = cpanelLcInstance();
    $username = cpanelLcUsername($instance->id);
    $log = cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [fn () => Http::failedConnection()],
        'listaccts' => [fn () => Http::failedConnection(), cpanelLcList($username)],
    ]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));
    expect(cpanelLcClaim($instance)->state)->toBe('unknown');

    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));
    expect(cpanelLcClaim($instance)->state)->toBe('unknown');

    cpanelLcProvisioner()->provision(cpanelLcInfo($instance));

    expect(cpanelLcFunctions($log))->toBe(['listpkgs', 'createacct', 'listaccts', 'listaccts'])
        ->and($log[2]['base'])->toBe('https://whm.example.test:2087/json-api/listaccts')
        ->and($log[2]['query'])->toBe(['api.version' => '1', 'searchtype' => 'user', 'searchmethod' => 'exact', 'search' => $username])
        ->and(cpanelLcClaim($instance)->state)->toBe('active')
        ->and(cpanelLcClaim($instance)->remote_id)->toBe($username)
        ->and(cpanelLcClaim($instance)->remote_name)->toBe($username.'.temp.example.test');
});

test('cpanel ambiguous create is created again only when the stored username is clearly absent', function () {
    $instance = cpanelLcInstance();
    $username = cpanelLcUsername($instance->id);
    $log = cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter'), cpanelLcPackages('starter')],
        'createacct' => [Http::response('gateway timeout', 504), cpanelLcOk('createacct')],
        'listaccts' => [cpanelLcList(null)],
    ]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));
    expect(cpanelLcClaim($instance)->state)->toBe('unknown');
    cpanelLcProvisioner()->provision(cpanelLcInfo($instance));

    expect(cpanelLcFunctions($log))->toBe(['listpkgs', 'createacct', 'listaccts', 'listpkgs', 'createacct'])
        ->and($log[4]['query']['username'])->toBe($username)
        ->and(cpanelLcClaim($instance)->state)->toBe('active');
});

test('cpanel reconciliation never adopts an account that does not match the recorded create', function (array $overrides) {
    $instance = cpanelLcInstance();
    $username = cpanelLcUsername($instance->id);
    $log = cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [fn () => Http::failedConnection()],
        'listaccts' => [cpanelLcList($username, $overrides)],
    ]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));

    expect(cpanelLcFunctions($log))->toBe(['listpkgs', 'createacct', 'listaccts'])
        ->and(cpanelLcClaim($instance)->state)->toBe('unknown')
        ->and(cpanelLcClaim($instance)->remote_name)->toBeNull();
})->with([
    'other owner' => [['owner' => 'reseller1']],
    'other package' => [['plan' => 'gold']],
    'created before the claim' => [['unix_startdate' => time() - 86400]],
    'missing start date' => [['unix_startdate' => null]],
]);

test('cpanel orchestrator moves an ambiguous create to manual review', function () {
    $instance = cpanelLcInstance();
    cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [fn () => Http::failedConnection()],
    ]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and(cpanelLcClaim($instance)->state)->toBe('unknown');
});

test('cpanel provider failure flag with http 200 is not marked active', function () {
    $instance = cpanelLcInstance();
    $log = cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [cpanelLcFail('createacct', 'Sorry, a group for that username already exists. '.CPANEL_LC_TOKEN)],
    ]);

    $exception = cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));

    expect(json_encode($exception->errors()).$exception->getMessage())->not->toContain(CPANEL_LC_TOKEN)
        ->and(cpanelLcClaim($instance)->state)->toBe('failed')
        ->and(cpanelLcFunctions($log))->toBe(['listpkgs', 'createacct']);
});

test('cpanel orchestrator fails a rejected create and reports the provider absent', function () {
    $instance = cpanelLcInstance();
    $log = cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [cpanelLcFail('createacct')],
    ]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::Failed)
        ->and(cpanelLcClaim($instance)->state)->toBe('failed')
        ->and(cpanelLcFunctions($log))->toBe(['listpkgs', 'createacct']);
});

test('cpanel retry after a rejected create rotates the username only when the old one is absent', function () {
    $instance = cpanelLcInstance();
    $first = cpanelLcUsername($instance->id);
    cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [cpanelLcFail('createacct')],
    ]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));

    Str::createRandomStringsUsing(static fn (int $length): string => str_repeat('m', $length));
    $log = cpanelLcFake([
        'listaccts' => [cpanelLcList(null)],
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [cpanelLcOk('createacct')],
    ]);
    cpanelLcProvisioner()->provision(cpanelLcInfo($instance));

    $claim = cpanelLcClaim($instance);
    expect(cpanelLcFunctions($log))->toBe(['listaccts', 'listpkgs', 'createacct'])
        ->and($log[0]['query']['search'])->toBe($first)
        ->and($log[2]['query']['username'])->toBe(cpanelLcUsername($instance->id, 'm'))
        ->and($claim->remote_id)->toBe(cpanelLcUsername($instance->id, 'm'))
        ->and($claim->state)->toBe('active');

    $other = cpanelLcInstance();
    cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [cpanelLcFail('createacct')],
    ]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($other)));
    $log = cpanelLcFake(['listaccts' => [cpanelLcList(cpanelLcClaim($other)->remote_id)]]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($other)));
    expect(cpanelLcFunctions($log))->toBe(['listaccts'])
        ->and(cpanelLcClaim($other)->state)->toBe('failed')
        ->and(cpanelLcClaim($other)->remote_id)->toBe(cpanelLcUsername($other->id, 'm'));
});

test('cpanel refuses a package that the token cannot create without calling createacct', function () {
    $instance = cpanelLcInstance(package: 'gold');
    $log = cpanelLcFake(['listpkgs' => [cpanelLcPackages('starter')]]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));

    expect(cpanelLcFunctions($log))->toBe(['listpkgs'])
        ->and(cpanelLcClaim($instance)->state)->toBe('failed');
});

test('cpanel refuses a product without a package before claiming or calling the provider', function () {
    $instance = cpanelLcInstance(package: ' ');
    $log = cpanelLcFake([]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));

    expect($log->count())->toBe(0)
        ->and(cpanelLcClaim($instance))->toBeNull();
});

test('cpanel refuses every provider call in the demo environment', function () {
    [$instance] = cpanelLcActive();
    $fresh = cpanelLcInstance();
    app()['env'] = 'demo';
    $log = cpanelLcFake([]);

    try {
        cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($fresh)));
        cpanelLcRefused(fn () => cpanelLcProvisioner()->suspend(cpanelLcInfo($instance)));
        cpanelLcRefused(fn () => cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance)));
        $health = cpanelLcProvisioner()->testServer(['api_url' => CPANEL_LC_URL, 'api_username' => 'root', 'api_token' => CPANEL_LC_TOKEN]);
    } finally {
        app()['env'] = 'testing';
    }

    expect($log->count())->toBe(0)
        ->and($health->ok)->toBeFalse()
        ->and(cpanelLcClaim($fresh)->state)->toBe('failed')
        ->and(cpanelLcClaim($instance)->state)->toBe('active');
});

test('cpanel suspend and unsuspend call the documented functions and track state', function () {
    [$instance, $claim] = cpanelLcActive();
    $username = $claim->remote_id;
    $log = cpanelLcFake([
        'suspendacct' => [cpanelLcOk('suspendacct')],
        'unsuspendacct' => [cpanelLcOk('unsuspendacct')],
    ]);

    cpanelLcProvisioner()->suspend(cpanelLcInfo($instance));
    expect(cpanelLcClaim($instance)->state)->toBe('suspended')
        ->and(cpanelLcClaim($instance)->revision)->toBe(2);
    cpanelLcProvisioner()->suspend(cpanelLcInfo($instance));
    cpanelLcProvisioner()->unsuspend(cpanelLcInfo($instance));
    cpanelLcProvisioner()->unsuspend(cpanelLcInfo($instance));

    expect(cpanelLcFunctions($log))->toBe(['suspendacct', 'unsuspendacct'])
        ->and($log[0]['base'])->toBe('https://whm.example.test:2087/json-api/suspendacct')
        ->and($log[0]['query'])->toBe(['api.version' => '1', 'user' => $username, 'reason' => 'Suspended by Agovena'])
        ->and($log[1]['base'])->toBe('https://whm.example.test:2087/json-api/unsuspendacct')
        ->and($log[1]['query'])->toBe(['api.version' => '1', 'user' => $username])
        ->and(cpanelLcClaim($instance)->state)->toBe('active')
        ->and(cpanelLcClaim($instance)->revision)->toBe(3);
});

test('cpanel suspend and unsuspend failures keep the stored state', function () {
    [$instance] = cpanelLcActive();
    cpanelLcFake(['suspendacct' => [cpanelLcFail('suspendacct'), fn () => Http::failedConnection()]]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->suspend(cpanelLcInfo($instance)));
    cpanelLcRefused(fn () => cpanelLcProvisioner()->suspend(cpanelLcInfo($instance)));
    expect(cpanelLcClaim($instance)->state)->toBe('active');

    cpanelLcFake(['suspendacct' => [cpanelLcOk('suspendacct')], 'unsuspendacct' => [cpanelLcOk('wrongcommand'), ['unexpected' => 'shape']]]);
    cpanelLcProvisioner()->suspend(cpanelLcInfo($instance));
    cpanelLcRefused(fn () => cpanelLcProvisioner()->unsuspend(cpanelLcInfo($instance)));
    cpanelLcRefused(fn () => cpanelLcProvisioner()->unsuspend(cpanelLcInfo($instance)));
    expect(cpanelLcClaim($instance)->state)->toBe('suspended');
});

test('cpanel terminate removes the account and keeps the identifier claimed', function () {
    [$instance, $claim] = cpanelLcActive();
    $log = cpanelLcFake(['removeacct' => [cpanelLcOk('removeacct')]]);

    cpanelLcProvisioner()->terminate(cpanelLcInfo($instance));
    cpanelLcProvisioner()->terminate(cpanelLcInfo($instance));
    $info = cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance));

    expect(cpanelLcFunctions($log))->toBe(['removeacct'])
        ->and($log[0]['base'])->toBe('https://whm.example.test:2087/json-api/removeacct')
        ->and($log[0]['query'])->toBe(['api.version' => '1', 'username' => $claim->remote_id])
        ->and(cpanelLcClaim($instance)->state)->toBe('terminated')
        ->and(cpanelLcClaim($instance)->remote_id)->toBe($claim->remote_id)
        ->and($info->status)->toBe('terminated');
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));
    expect($log->count())->toBe(1);
});

test('cpanel terminate failures keep the account and ambiguous claims are never removed', function () {
    [$instance] = cpanelLcActive();
    cpanelLcFake(['removeacct' => [cpanelLcFail('removeacct')]]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->terminate(cpanelLcInfo($instance)));
    expect(cpanelLcClaim($instance)->state)->toBe('active');

    $ambiguous = cpanelLcInstance();
    cpanelLcFake(['listpkgs' => [cpanelLcPackages('starter')], 'createacct' => [fn () => Http::failedConnection()]]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($ambiguous)));
    $log = cpanelLcFake([]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->terminate(cpanelLcInfo($ambiguous)));
    expect(cpanelLcClaim($ambiguous)->state)->toBe('unknown')
        ->and($log->count())->toBe(0);
});

test('cpanel terminate closes a rejected create and an unclaimed service without network', function () {
    $rejected = cpanelLcInstance();
    cpanelLcFake(['listpkgs' => [cpanelLcPackages('starter')], 'createacct' => [cpanelLcFail('createacct')]]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($rejected)));
    $unclaimed = cpanelLcInstance();
    $log = cpanelLcFake([]);

    cpanelLcProvisioner()->terminate(cpanelLcInfo($rejected));
    cpanelLcProvisioner()->terminate(cpanelLcInfo($unclaimed));

    expect($log->count())->toBe(0)
        ->and(cpanelLcClaim($rejected)->state)->toBe('terminated')
        ->and(cpanelLcClaim($unclaimed))->toBeNull();
});

test('cpanel change plan uses changepackage and records the package', function () {
    [$instance, $claim] = cpanelLcActive();
    $log = cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter', 'business')],
        'changepackage' => [cpanelLcOk('changepackage')],
    ]);

    cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), ['id' => '2', 'provider_settings' => ['package' => 'business']]);
    cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), ['id' => '2', 'provider_settings' => ['package' => 'business']]);

    expect(cpanelLcFunctions($log))->toBe(['listpkgs', 'changepackage'])
        ->and($log[0]['query'])->toBe(['api.version' => '1', 'want' => 'creatable'])
        ->and($log[1]['base'])->toBe('https://whm.example.test:2087/json-api/changepackage')
        ->and($log[1]['query'])->toBe(['api.version' => '1', 'user' => $claim->remote_id, 'pkg' => 'business'])
        ->and(cpanelLcClaim($instance)->plan)->toBe('business')
        ->and(cpanelLcClaim($instance)->state)->toBe('active');
});

test('cpanel change plan failures keep the recorded package', function () {
    [$instance] = cpanelLcActive();
    $log = cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter', 'business'), cpanelLcPackages('starter')],
        'changepackage' => [cpanelLcFail('changepackage')],
    ]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), ['id' => '2', 'provider_settings' => ['package' => 'business']]));
    cpanelLcRefused(fn () => cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), ['id' => '3', 'provider_settings' => ['package' => 'business']]));
    cpanelLcRefused(fn () => cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), ['id' => '4', 'provider_settings' => []]));
    cpanelLcRefused(fn () => cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), 'business'));

    expect(cpanelLcFunctions($log))->toBe(['listpkgs', 'changepackage', 'listpkgs'])
        ->and(cpanelLcClaim($instance)->plan)->toBe('starter');
});

test('cpanel plan change to another server is refused without network', function () {
    [$instance] = cpanelLcActive();
    $log = cpanelLcFake([]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), [
        'id' => '2',
        'provider_settings' => ['package' => 'business'],
        'server_settings' => ['api_url' => 'https://other.example.test:2087', 'api_username' => 'root', 'api_token' => 'x'],
    ]));

    expect($log->count())->toBe(0)
        ->and(cpanelLcClaim($instance)->plan)->toBe('starter');
});

test('cpanel sync maps the documented suspended flag and rejects failures and malformed data', function () {
    [$instance, $claim] = cpanelLcActive();
    $username = $claim->remote_id;
    $log = cpanelLcFake([
        'accountsummary' => [
            cpanelLcSummary($username, ['suspended' => 1]),
            cpanelLcSummary($username, ['suspended' => 0, 'plan' => 'business']),
            cpanelLcFail('accountsummary', 'Account does not exist'),
            cpanelLcSummary($username, ['suspended' => 'yes']),
            cpanelLcSummary('someoneelse'),
            cpanelLcOk('accountsummary', ['acct' => []]),
            cpanelLcOk('accountsummary', ['acct' => [cpanelLcAccount($username), cpanelLcAccount($username)]]),
            cpanelLcOk('listaccts', ['acct' => [cpanelLcAccount($username)]]),
            fn () => Http::failedConnection(),
        ],
    ]);

    expect(cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance))->status)->toBe('suspended')
        ->and(cpanelLcClaim($instance)->state)->toBe('suspended')
        ->and(cpanelLcProvisioner()->poll(cpanelLcInfo($instance))->status)->toBe('active')
        ->and(cpanelLcClaim($instance)->state)->toBe('active')
        ->and(cpanelLcClaim($instance)->plan)->toBe('business');

    foreach (range(1, 7) as $attempt) {
        cpanelLcRefused(fn () => cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance)));
    }
    expect(cpanelLcClaim($instance)->state)->toBe('active')
        ->and(cpanelLcFunctions($log))->toBe(array_fill(0, 9, 'accountsummary'));
});

test('cpanel sync of an unverified claim requires reconciliation without network', function () {
    $instance = cpanelLcInstance();
    cpanelLcFake(['listpkgs' => [cpanelLcPackages('starter')], 'createacct' => [Http::response('bad gateway', 502)]]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)));
    $log = cpanelLcFake([]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance)));

    expect($log->count())->toBe(0)
        ->and(cpanelLcClaim($instance)->state)->toBe('unknown');
});

test('cpanel lifecycle is refused without network when the endpoint is re-pointed', function () {
    [$instance] = cpanelLcActive();

    foreach (['https://other.example.test:2087', 'https://whm.example.test:2083', 'http://whm.example.test:2087'] as $url) {
        cpanelLcPointAt($url);
        $log = cpanelLcFake([]);
        foreach ([
            fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)),
            fn () => cpanelLcProvisioner()->suspend(cpanelLcInfo($instance)),
            fn () => cpanelLcProvisioner()->unsuspend(cpanelLcInfo($instance)),
            fn () => cpanelLcProvisioner()->terminate(cpanelLcInfo($instance)),
            fn () => cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), ['id' => '2', 'provider_settings' => ['package' => 'business']]),
            fn () => cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance)),
        ] as $operation) {
            cpanelLcRefused($operation);
        }
        expect($log->count())->toBe(0);
    }

    cpanelLcPointAt('https://WHM.example.test:2087/');
    cpanelLcFake(['suspendacct' => [cpanelLcOk('suspendacct')]]);
    cpanelLcProvisioner()->suspend(cpanelLcInfo($instance));
    expect(cpanelLcClaim($instance)->state)->toBe('suspended');
});

test('cpanel hand written provider mapping without a claim row is refused without network', function () {
    $instance = cpanelLcInstance(['provider_mapping' => ['provider_id' => 'cpuser01', 'domain' => 'shop.example.test']]);
    $instance->forceFill(['external_ref' => 'cpuser01'])->save();
    $log = cpanelLcFake([]);

    foreach ([
        fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($instance)),
        fn () => cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance)),
        fn () => cpanelLcProvisioner()->poll(cpanelLcInfo($instance)),
        fn () => cpanelLcProvisioner()->suspend(cpanelLcInfo($instance)),
        fn () => cpanelLcProvisioner()->unsuspend(cpanelLcInfo($instance)),
        fn () => cpanelLcProvisioner()->terminate(cpanelLcInfo($instance)),
        fn () => cpanelLcProvisioner()->changePlan(cpanelLcInfo($instance), ['id' => '2', 'provider_settings' => ['package' => 'business']]),
    ] as $operation) {
        cpanelLcRefused($operation);
    }

    expect($log->count())->toBe(0)
        ->and(cpanelLcClaim($instance))->toBeNull()
        ->and(cpanelLcProvisioner()->panel(cpanelLcInfo($instance)))->toBeNull()
        ->and(cpanelLcProvisioner()->actions(cpanelLcInfo($instance)))->toBe([]);
});

test('cpanel sync without any claim reports absence without network', function () {
    $instance = cpanelLcInstance();
    $log = cpanelLcFake([]);

    $info = cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and($info->meta['provider_reconciliation'] ?? null)->toBe('absent')
        ->and($info->meta)->not->toHaveKey('provider_mapping');
});

test('cpanel stale revision between read and write never overwrites the row', function () {
    [$instance, $claim] = cpanelLcActive();
    cpanelLcFake([
        'suspendacct' => [function () use ($claim) {
            DB::table('cpanel_accounts')->where('id', $claim->id)->update(['revision' => 7]);

            return Http::response(cpanelLcOk('suspendacct'), 200);
        }],
    ]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->suspend(cpanelLcInfo($instance)));

    expect(cpanelLcClaim($instance)->state)->toBe('active')
        ->and(cpanelLcClaim($instance)->revision)->toBe(7);

    $fresh = cpanelLcInstance();
    cpanelLcFake([
        'listpkgs' => [cpanelLcPackages('starter')],
        'createacct' => [function () use ($fresh) {
            DB::table('cpanel_accounts')->where('service_instance_id', $fresh->id)->update(['state' => 'failed', 'revision' => 5]);

            return Http::response(cpanelLcOk('createacct'), 200);
        }],
    ]);
    cpanelLcRefused(fn () => cpanelLcProvisioner()->provision(cpanelLcInfo($fresh)));
    expect(cpanelLcClaim($fresh)->state)->toBe('failed')
        ->and(cpanelLcClaim($fresh)->revision)->toBe(5);
});

test('cpanel sync refuses a domain that another claim already holds', function () {
    [$first, $firstClaim] = cpanelLcActive();
    [$second, $secondClaim] = cpanelLcActive();
    $firstClaim->forceFill(['remote_name' => 'shop.example.test'])->save();
    cpanelLcFake(['accountsummary' => [cpanelLcSummary($secondClaim->remote_id, ['domain' => 'shop.example.test'])]]);

    cpanelLcRefused(fn () => cpanelLcProvisioner()->syncStatus(cpanelLcInfo($second)));

    expect(cpanelLcClaim($second)->remote_name)->toBeNull()
        ->and(cpanelLcClaim($second)->revision)->toBe(1)
        ->and(cpanelLcClaim($first)->remote_name)->toBe('shop.example.test');
});

test('cpanel never leaks the api token in exceptions, info or persisted rows', function () {
    [$instance] = cpanelLcActive();
    cpanelLcFake([
        'accountsummary' => [cpanelLcSummary(cpanelLcClaim($instance)->remote_id), Http::response(['error' => CPANEL_LC_TOKEN], 500)],
    ]);

    $info = cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance));
    $exception = cpanelLcRefused(fn () => cpanelLcProvisioner()->syncStatus(cpanelLcInfo($instance)));
    $panel = cpanelLcProvisioner()->panel(cpanelLcInfo($instance));

    expect(json_encode([$info->meta, $info->serverSettings, $info->providerSettings, $info->externalRef]))->not->toContain(CPANEL_LC_TOKEN)
        ->and(json_encode($exception->errors()).$exception->getMessage())->not->toContain(CPANEL_LC_TOKEN)
        ->and(json_encode($panel))->not->toContain(CPANEL_LC_TOKEN)
        ->and(json_encode(CPanelAccount::query()->get()->toArray()))->not->toContain(CPANEL_LC_TOKEN)
        ->and(json_encode($instance->fresh()->meta))->not->toContain(CPANEL_LC_TOKEN);
});

test('cpanel panel shows the claimed account without network or credentials', function () {
    [$instance, $claim] = cpanelLcActive();
    $log = cpanelLcFake([]);

    $panel = cpanelLcProvisioner()->panel(cpanelLcInfo($instance));

    expect($log->count())->toBe(0)
        ->and($panel)->not->toBeNull()
        ->and(collect($panel->fields)->pluck('value')->all())->toContain($claim->remote_id, 'starter')
        ->and(cpanelLcProvisioner()->actions(cpanelLcInfo($instance)))->toBe([]);
});

test('cpanel health uses the documented read-only version call and checks metadata', function () {
    $log = cpanelLcFake(['version' => [cpanelLcOk('version', ['version' => '11.130.0.1']), cpanelLcFail('version')]]);
    $settings = ['api_url' => CPANEL_LC_URL, 'api_username' => 'root', 'api_token' => CPANEL_LC_TOKEN, 'verify_tls' => true];

    expect(cpanelLcProvisioner()->testServer($settings)->ok)->toBeTrue()
        ->and(cpanelLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(cpanelLcFunctions($log))->toBe(['version', 'version'])
        ->and($log[0]['base'])->toBe('https://whm.example.test:2087/json-api/version')
        ->and($log[0]['query'])->toBe(['api.version' => '1'])
        ->and($log[0]['authorization'])->toBe('whm root:'.CPANEL_LC_TOKEN);

    $log = cpanelLcFake([]);
    expect(cpanelLcProvisioner()->testServer(['api_url' => CPANEL_LC_URL.'/json-api'] + $settings)->ok)->toBeFalse()
        ->and(cpanelLcProvisioner()->testServer(['api_token' => ''] + $settings)->ok)->toBeFalse()
        ->and(cpanelLcProvisioner()->testServer(['verify_tls' => 'invalid'] + $settings)->ok)->toBeFalse()
        ->and($log->count())->toBe(0);
});
