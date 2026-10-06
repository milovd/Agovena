<?php

declare(strict_types=1);

use Agovena\Extensions\Plesk\PleskAccount;
use Agovena\Extensions\Plesk\PleskEndpoint;
use Agovena\Extensions\Plesk\PleskProvisioner;
use Agovena\Modules\Provisioning\EloquentProvisionedServiceResolver;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ServiceInstanceRuntimeSecretStore;
use Agovena\Modules\Provisioning\Support\ServerProviderException;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Packages\OptionalPackagesPath;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const PLESK_LC_TOKEN = 'plesk-key-SECRET-7d21c9';
const PLESK_LC_URL = 'https://plesk.example.test:8443';
const PLESK_LC_AGENT = 'https://plesk.example.test:8443/enterprise/control/agent.php';
const PLESK_LC_IP = '203.0.113.10';

beforeEach(function (): void {
    installAndEnableModule('provisioning');
    app(ExtensionManager::class)->discover();
    installAndEnableExtension('plesk');
    app()->forgetInstance(PleskProvisioner::class);
    pleskLcPointAt(PLESK_LC_URL);
    pleskLcRegisterFake();
    Str::createRandomStringsUsing(static fn (int $length): string => str_repeat('K', $length));
});

afterEach(function (): void {
    Str::createRandomStringsNormally();
});

function pleskLcPointAt(string $url): void
{
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('plesk', 'api_url', $url);
    $settings->set('plesk', 'api_token', PLESK_LC_TOKEN, secret: true);
    $settings->set('plesk', 'ip_address', PLESK_LC_IP);
    $settings->set('plesk', 'verify_tls', true);
    $settings->set('plesk', 'timeout', '20');
}

function pleskLcProvisioner(): PleskProvisioner
{
    return app(PleskProvisioner::class);
}

/** @param array<string, mixed> $meta */
function pleskLcInstance(array $meta = [], string $plan = 'Starter', ?string $domain = null): ServiceInstance
{
    static $sequence = 0;
    $sequence++;
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-PLLC-'.$sequence.'-'.bin2hex(random_bytes(3)),
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'plesk',
        'customer_email' => 'buyer'.$sequence.'@example.test',
        'customer_name' => 'Buyer '.$sequence,
        'meta' => array_merge(['label' => 'Hosting'], $meta),
    ]);
    app(ServiceInstanceRuntimeSecretStore::class)->put($instance->id, null, [
        'service_plan' => $plan,
        'domain' => $domain ?? 'site'.$sequence.'-'.bin2hex(random_bytes(2)).'.example.test',
    ]);

    return $instance->fresh();
}

function pleskLcInfo(ServiceInstance $instance): ServiceInstanceInfo
{
    return EloquentProvisionedServiceResolver::info($instance->fresh());
}

/** The login Agovena generates while random strings are faked to a fixed letter. */
function pleskLcLogin(int $instanceId, string $letter = 'k'): string
{
    return 'agv'.str_repeat($letter, 8).base_convert((string) $instanceId, 10, 36);
}

function pleskLcDomain(ServiceInstance $instance): string
{
    return (string) app(ServiceInstanceRuntimeSecretStore::class)->get($instance->id)['provider_settings']['domain'];
}

function pleskLcPacket(string $operator, string $operation, string $results): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><packet version="1.6.9.1"><'.$operator.'><'.$operation.'>'
        .$results.'</'.$operation.'></'.$operator.'></packet>';
}

function pleskLcOk(string $operator, string $operation, string $extra = ''): string
{
    return pleskLcPacket($operator, $operation, '<result><status>ok</status>'.$extra.'</result>');
}

function pleskLcError(string $operator, string $operation, int $code): string
{
    return pleskLcPacket($operator, $operation, '<result><status>error</status><errcode>'.$code.'</errcode><errtext>Failed</errtext></result>');
}

function pleskLcSystemError(int $code): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><packet version="1.6.9.1"><system><status>error</status><errcode>'
        .$code.'</errcode><errtext>Failed</errtext></system></packet>';
}

function pleskLcPlans(string ...$names): string
{
    $results = '';
    foreach ($names as $index => $name) {
        $results .= '<result><status>ok</status><id>'.($index + 1).'</id><name>'.$name.'</name><guid>guid-'.$name.'</guid></result>';
    }

    return pleskLcPacket('service-plan', 'get', $results);
}

function pleskLcSubscription(int $id, string $name, int $status = 0, int $ownerId = 7, string $plan = 'Starter'): string
{
    return pleskLcOk('webspace', 'get', '<filter-id>'.$id.'</filter-id><id>'.$id.'</id><data><gen_info><cr_date>2026-10-05</cr_date>'
        .'<name>'.$name.'</name><ascii-name>'.$name.'</ascii-name><status>'.$status.'</status><real_size>0</real_size>'
        .'<owner-id>'.$ownerId.'</owner-id><htype>vrt_hst</htype></gen_info><subscriptions><subscription><locked>false</locked>'
        .'<synchronized>true</synchronized><plan><plan-guid>guid-'.$plan.'</plan-guid></plan></subscription></subscriptions></data>');
}

function pleskLcCustomer(int $id, string $login): string
{
    return pleskLcOk('customer', 'get', '<filter-id>'.$login.'</filter-id><id>'.$id.'</id><data><gen_info><pname>Buyer</pname><login>'.$login.'</login></gen_info></data>');
}

/** Shared state of the scripted Plesk fake that beforeEach registers once per test. */
function pleskLcState(): stdClass
{
    static $state = null;

    return $state ??= new stdClass;
}

function pleskLcRegisterFake(): void
{
    pleskLcFake([]);
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $state = pleskLcState();
        $operation = preg_match('#<packet><([a-z_-]+)><([a-z_-]+)[ />]#', $request->body(), $matches) === 1
            ? $matches[1].'.'.$matches[2]
            : 'unknown';
        $state->log[] = [
            'operation' => $operation,
            'method' => $request->method(),
            'url' => $request->url(),
            'body' => $request->body(),
            'key' => $request->header('KEY')[0] ?? null,
            'type' => $request->header('Content-Type')[0] ?? null,
        ];
        if (($state->script[$operation] ?? []) === []) {
            return Http::response('unexpected '.$operation, 599);
        }
        $next = array_shift($state->script[$operation]);

        return $next instanceof Closure ? $next($request) : Http::response($next, 200, ['Content-Type' => 'text/xml']);
    });
}

/**
 * Replaces the Plesk script. Each "operator.operation" maps to a queue of responses.
 *
 * @param  array<string, list<mixed>>  $script
 */
function pleskLcFake(array $script): ArrayObject
{
    $state = pleskLcState();
    $state->script = $script;
    $state->log = new ArrayObject;

    return $state->log;
}

/** @return list<string> */
function pleskLcOperations(ArrayObject $log): array
{
    return array_values(array_map(static fn (array $entry): string => $entry['operation'], $log->getArrayCopy()));
}

function pleskLcClaim(ServiceInstance $instance): ?PleskAccount
{
    return PleskAccount::query()->where('service_instance_id', $instance->id)->first();
}

/** @return array<string, list<mixed>> */
function pleskLcCreateScript(): array
{
    return [
        'service-plan.get' => [pleskLcPlans('Starter', 'Business')],
        'customer.add' => [pleskLcOk('customer', 'add', '<id>7</id><guid>c-guid</guid>')],
        'webspace.add' => [pleskLcOk('webspace', 'add', '<id>42</id><guid>w-guid</guid>')],
    ];
}

/** @return array{0: ServiceInstance, 1: PleskAccount} */
function pleskLcActive(): array
{
    $instance = pleskLcInstance();
    pleskLcFake(pleskLcCreateScript());
    pleskLcProvisioner()->provision(pleskLcInfo($instance));

    return [$instance, pleskLcClaim($instance)];
}

function pleskLcRefused(callable $operation): ValidationException
{
    try {
        $operation();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('instance');

        return $exception;
    }

    throw new RuntimeException('Expected the Plesk operation to be refused.');
}

test('plesk migration creates the claim table with unique identity columns and is reversible', function () {
    expect(Schema::hasColumns('plesk_accounts', [
        'id', 'service_instance_id', 'endpoint', 'remote_id', 'remote_name', 'customer_login', 'customer_id',
        'plan', 'state', 'revision', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $row = ['service_instance_id' => 1, 'endpoint' => PLESK_LC_URL, 'remote_id' => '1', 'remote_name' => 'a.example.test', 'customer_login' => 'agva', 'state' => 'active', 'created_at' => now(), 'updated_at' => now()];
    DB::table('plesk_accounts')->insert($row);
    foreach ([
        ['remote_id' => '2', 'remote_name' => 'b.example.test', 'customer_login' => 'agvb'],
        ['service_instance_id' => 2, 'remote_name' => 'b.example.test', 'customer_login' => 'agvb'],
        ['service_instance_id' => 2, 'remote_id' => '2', 'customer_login' => 'agvb'],
        ['service_instance_id' => 2, 'remote_id' => '2', 'remote_name' => 'b.example.test'],
    ] as $duplicate) {
        expect(fn () => DB::table('plesk_accounts')->insert($duplicate + $row))->toThrow(QueryException::class);
    }

    $migration = require OptionalPackagesPath::extensionsRoot().'/provisioning/plesk/database/migrations/2026_10_05_000000_create_plesk_accounts_table.php';
    $migration->down();
    expect(Schema::hasTable('plesk_accounts'))->toBeFalse();
    $migration->up();
    expect(Schema::hasTable('plesk_accounts'))->toBeTrue();
});

test('plesk endpoint identity is normalized and rejects paths and credentials', function () {
    expect(PleskEndpoint::normalize('HTTPS://Plesk.Example.Test:8443/'))->toBe('https://plesk.example.test:8443')
        ->and(PleskEndpoint::normalize('https://plesk.example.test'))->toBe('https://plesk.example.test:443');

    foreach (['', 'ftp://plesk.example.test', 'https://user:pass@plesk.example.test', 'https://plesk.example.test/enterprise/control/agent.php', 'https://plesk.example.test?x=1'] as $url) {
        expect(fn () => PleskEndpoint::normalize($url))->toThrow(ServerProviderException::class);
    }
});

test('plesk create claims first, sends the documented packets once and a retry is idempotent', function () {
    $instance = pleskLcInstance();
    $domain = pleskLcDomain($instance);
    $login = pleskLcLogin($instance->id);
    $script = pleskLcCreateScript();
    $script['customer.add'] = [function () use ($instance) {
        expect(pleskLcClaim($instance)?->state)->toBe('creating');

        return Http::response(pleskLcOk('customer', 'add', '<id>7</id><guid>c-guid</guid>'), 200);
    }];
    $log = pleskLcFake($script);

    pleskLcProvisioner()->provision(pleskLcInfo($instance));

    expect(pleskLcOperations($log))->toBe(['service-plan.get', 'customer.add', 'webspace.add'])
        ->and(pleskLcClaim($instance)->only(['endpoint', 'remote_id', 'remote_name', 'customer_login', 'customer_id', 'plan', 'state', 'revision']))->toBe([
            'endpoint' => PLESK_LC_URL,
            'remote_id' => '42',
            'remote_name' => $domain,
            'customer_login' => $login,
            'customer_id' => 7,
            'plan' => 'Starter',
            'state' => 'active',
            'revision' => 2,
        ]);
    foreach ($log as $entry) {
        expect($entry['method'])->toBe('POST')
            ->and($entry['url'])->toBe(PLESK_LC_AGENT)
            ->and($entry['key'])->toBe(PLESK_LC_TOKEN)
            ->and($entry['type'])->toBe('text/xml');
    }
    expect($log[0]['body'])->toContain('<packet><service-plan><get><filter/></get></service-plan></packet>')
        ->and($log[1]['body'])->toContain('<gen_info><pname>Buyer ')
        ->toContain('<login>'.$login.'</login><passwd>')
        ->toContain('<email>'.$instance->customer_email.'</email>')
        ->and($log[2]['body'])->toContain('<gen_setup><name>'.$domain.'</name><owner-id>7</owner-id><htype>vrt_hst</htype><ip_address>'.PLESK_LC_IP.'</ip_address></gen_setup>')
        ->toContain('<property><name>ftp_login</name><value>'.$login.'</value></property>')
        ->toContain('<plan-name>Starter</plan-name>');

    $retry = pleskLcFake([]);
    pleskLcProvisioner()->provision(pleskLcInfo($instance));
    expect($retry->count())->toBe(0);
});

test('plesk orchestrated provisioning activates the service without persisting secrets', function () {
    $instance = pleskLcInstance();
    $script = pleskLcCreateScript();
    $script['webspace.get'] = [pleskLcSubscription(42, pleskLcDomain($instance))];
    $log = pleskLcFake($script);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->status)->toBe(ServiceInstanceStatus::Active)
        ->and($result->external_ref)->toBe('42');
    preg_match('#<passwd>([^<]+)</passwd>#', $log[1]['body'], $password);
    $persisted = json_encode([$result->fresh()->getAttributes(), pleskLcClaim($instance)->getAttributes()]);
    expect($password[1] ?? '')->not->toBe('')
        ->and($persisted)->not->toContain(PLESK_LC_TOKEN)
        ->not->toContain($password[1])
        ->and(strtolower((string) $persisted))->not->toContain('passw');
});

test('plesk refuses a second service claiming the same domain without network', function () {
    [$first] = pleskLcActive();
    $second = pleskLcInstance(domain: pleskLcDomain($first));
    $log = pleskLcFake([]);

    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($second)));

    expect($log->count())->toBe(0)
        ->and(pleskLcClaim($second))->toBeNull();
});

test('plesk create timeout is never blindly retried and reconciles by the stored login and domain', function () {
    $instance = pleskLcInstance();
    $login = pleskLcLogin($instance->id);
    pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'customer.add' => [fn () => Http::failedConnection()],
    ]);
    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));
    expect(pleskLcClaim($instance)->state)->toBe('unknown');

    $log = pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'customer.get' => [pleskLcCustomer(7, $login)],
        'webspace.get' => [pleskLcError('webspace', 'get', 1013)],
        'webspace.add' => [pleskLcOk('webspace', 'add', '<id>42</id><guid>w-guid</guid>')],
    ]);
    pleskLcProvisioner()->provision(pleskLcInfo($instance));

    expect(pleskLcOperations($log))->toBe(['service-plan.get', 'customer.get', 'webspace.get', 'webspace.add'])
        ->and($log[1]['body'])->toContain('<filter><login>'.$login.'</login></filter>')
        ->and($log[2]['body'])->toContain('<filter><name>'.pleskLcDomain($instance).'</name></filter>')
        ->and(pleskLcClaim($instance)->only(['state', 'customer_id', 'remote_id']))->toBe(['state' => 'active', 'customer_id' => 7, 'remote_id' => '42']);
});

test('plesk reconciliation accepts only a subscription owned by the customer agovena created', function () {
    $instance = pleskLcInstance();
    pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'customer.add' => [pleskLcOk('customer', 'add', '<id>7</id>')],
        'webspace.add' => [fn () => Http::response('', 504)],
    ]);
    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));
    expect(pleskLcClaim($instance)->only(['state', 'customer_id', 'remote_id']))
        ->toBe(['state' => 'unknown', 'customer_id' => 7, 'remote_id' => 'pending:'.$instance->id]);

    $foreign = pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'webspace.get' => [pleskLcSubscription(42, pleskLcDomain($instance), ownerId: 99)],
    ]);
    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));
    expect(pleskLcOperations($foreign))->toBe(['service-plan.get', 'webspace.get'])
        ->and(pleskLcClaim($instance)->state)->toBe('unknown');

    $owned = pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'webspace.get' => [pleskLcSubscription(42, pleskLcDomain($instance))],
    ]);
    pleskLcProvisioner()->provision(pleskLcInfo($instance));
    expect(pleskLcOperations($owned))->toBe(['service-plan.get', 'webspace.get'])
        ->and(pleskLcClaim($instance)->only(['state', 'remote_id']))->toBe(['state' => 'active', 'remote_id' => '42']);
});

test('plesk ambiguous reconciliation read keeps the claim unknown and creates nothing', function () {
    $instance = pleskLcInstance();
    pleskLcFake(['service-plan.get' => [pleskLcPlans('Starter')], 'customer.add' => [fn () => Http::failedConnection()]]);
    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));

    foreach ([fn () => Http::response('', 500), '<packet><customer><get/></customer></packet>', pleskLcError('customer', 'get', 1006)] as $read) {
        $log = pleskLcFake(['service-plan.get' => [pleskLcPlans('Starter')], 'customer.get' => [$read]]);
        pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));
        expect(pleskLcOperations($log))->toBe(['service-plan.get', 'customer.get'])
            ->and(pleskLcClaim($instance)->state)->toBe('unknown');
    }
});

test('plesk orchestrator moves an ambiguous create to manual review', function () {
    $instance = pleskLcInstance();
    pleskLcFake(['service-plan.get' => [pleskLcPlans('Starter')], 'customer.add' => [fn () => Http::failedConnection()]]);

    $result = app(ProvisioningOrchestrator::class)->provision($instance);

    expect($result->fresh()->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and(pleskLcClaim($instance)->state)->toBe('unknown');
});

test('plesk provider error status with http 200 is never marked active', function () {
    $instance = pleskLcInstance();
    pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'customer.add' => [pleskLcOk('customer', 'add', '<id>7</id>')],
        'webspace.add' => [pleskLcError('webspace', 'add', 1007)],
    ]);

    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));

    expect(pleskLcClaim($instance)->only(['state', 'customer_id', 'remote_id']))
        ->toBe(['state' => 'failed', 'customer_id' => 7, 'remote_id' => 'pending:'.$instance->id]);
});

test('plesk rejected customer login is rotated so a foreign customer is never adopted', function () {
    $instance = pleskLcInstance();
    pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'customer.add' => [function () {
            Str::createRandomStringsUsing(static fn (int $length): string => str_repeat('M', $length));

            return Http::response(pleskLcError('customer', 'add', 1007), 200);
        }],
    ]);
    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));
    $rotated = pleskLcLogin($instance->id, 'm');
    expect(pleskLcClaim($instance)->only(['state', 'customer_login', 'customer_id']))
        ->toBe(['state' => 'failed', 'customer_login' => $rotated, 'customer_id' => null]);

    $log = pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'customer.get' => [pleskLcError('customer', 'get', 1013)],
        'customer.add' => [pleskLcOk('customer', 'add', '<id>8</id>')],
        'webspace.get' => [pleskLcError('webspace', 'get', 1013)],
        'webspace.add' => [pleskLcOk('webspace', 'add', '<id>42</id>')],
    ]);
    pleskLcProvisioner()->provision(pleskLcInfo($instance));

    expect(pleskLcOperations($log))->toBe(['service-plan.get', 'customer.get', 'customer.add', 'webspace.get', 'webspace.add'])
        ->and($log[1]['body'])->toContain('<login>'.$rotated.'</login>')
        ->and(pleskLcClaim($instance)->only(['state', 'customer_id']))->toBe(['state' => 'active', 'customer_id' => 8]);
});

test('plesk refuses a plan that does not exist on the server before creating a customer', function () {
    $instance = pleskLcInstance(plan: 'Missing');
    $log = pleskLcFake(['service-plan.get' => [pleskLcPlans('Starter')]]);

    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));

    expect(pleskLcOperations($log))->toBe(['service-plan.get'])
        ->and(pleskLcClaim($instance)->state)->toBe('failed');
});

test('plesk refuses a product without plan, domain or server ip before claiming', function (array $provider, ?string $ip) {
    $instance = pleskLcInstance();
    app(ServiceInstanceRuntimeSecretStore::class)->put($instance->id, null, $provider);
    if ($ip !== null) {
        app(ExtensionSettingsRepository::class)->set('plesk', 'ip_address', $ip);
    }
    $log = pleskLcFake([]);

    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));

    expect($log->count())->toBe(0)
        ->and(pleskLcClaim($instance))->toBeNull();
})->with([
    'no plan' => [['domain' => 'shop.example.test'], null],
    'invalid domain' => [['service_plan' => 'Starter', 'domain' => 'not a domain'], null],
    'invalid ip' => [['service_plan' => 'Starter', 'domain' => 'shop.example.test'], 'nope'],
]);

test('plesk refuses every provider call in the demo environment', function () {
    [$instance] = pleskLcActive();
    $fresh = pleskLcInstance();
    app()['env'] = 'demo';
    $log = pleskLcFake([]);

    try {
        pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($fresh)));
        pleskLcRefused(fn () => pleskLcProvisioner()->suspend(pleskLcInfo($instance)));
        pleskLcRefused(fn () => pleskLcProvisioner()->syncStatus(pleskLcInfo($instance)));
        $health = pleskLcProvisioner()->testServer(['api_url' => PLESK_LC_URL, 'api_token' => PLESK_LC_TOKEN]);
    } finally {
        app()['env'] = 'testing';
    }

    expect($log->count())->toBe(0)
        ->and($health->ok)->toBeFalse()
        ->and(pleskLcClaim($fresh)->state)->toBe('failed')
        ->and(pleskLcClaim($instance)->state)->toBe('active');
});

test('plesk suspend and unsuspend set the documented subscription status and track state', function () {
    [$instance] = pleskLcActive();
    $log = pleskLcFake([
        'webspace.set' => [
            pleskLcOk('webspace', 'set', '<filter-id>42</filter-id><id>42</id>'),
            pleskLcOk('webspace', 'set', '<filter-id>42</filter-id><id>42</id>'),
        ],
    ]);

    pleskLcProvisioner()->suspend(pleskLcInfo($instance));
    expect(pleskLcClaim($instance)->state)->toBe('suspended');
    pleskLcProvisioner()->suspend(pleskLcInfo($instance));
    pleskLcProvisioner()->unsuspend(pleskLcInfo($instance));

    expect(pleskLcClaim($instance)->state)->toBe('active')
        ->and(pleskLcOperations($log))->toBe(['webspace.set', 'webspace.set'])
        ->and($log[0]['body'])->toContain('<filter><id>42</id></filter><values><gen_setup><status>16</status></gen_setup></values>')
        ->and($log[1]['body'])->toContain('<values><gen_setup><status>0</status></gen_setup></values>');
});

test('plesk suspend and unsuspend failures keep the stored state', function () {
    [$instance] = pleskLcActive();
    foreach ([pleskLcError('webspace', 'set', 1023), pleskLcOk('webspace', 'set', '<id>43</id>'), pleskLcSystemError(1001)] as $response) {
        pleskLcFake(['webspace.set' => [$response]]);
        pleskLcRefused(fn () => pleskLcProvisioner()->suspend(pleskLcInfo($instance)));
        expect(pleskLcClaim($instance)->state)->toBe('active');
    }
});

test('plesk terminate deletes the owned subscription and the customer agovena created', function () {
    [$instance] = pleskLcActive();
    $log = pleskLcFake([
        'webspace.del' => [pleskLcOk('webspace', 'del', '<filter-id>42</filter-id><id>42</id>')],
        'webspace.get' => [pleskLcPacket('webspace', 'get', '<result><status>ok</status><filter-id>7</filter-id></result>')],
        'customer.del' => [pleskLcOk('customer', 'del', '<filter-id>7</filter-id><id>7</id>')],
    ]);

    pleskLcProvisioner()->terminate(pleskLcInfo($instance));
    pleskLcProvisioner()->terminate(pleskLcInfo($instance));

    expect(pleskLcOperations($log))->toBe(['webspace.del', 'webspace.get', 'customer.del'])
        ->and($log[0]['body'])->toContain('<del><filter><id>42</id></filter></del>')
        ->and($log[1]['body'])->toContain('<filter><owner-id>7</owner-id></filter>')
        ->and($log[2]['body'])->toContain('<del><filter><id>7</id></filter></del>')
        ->and(pleskLcClaim($instance)->only(['state', 'customer_id', 'remote_id']))->toBe(['state' => 'terminated', 'customer_id' => null, 'remote_id' => '42']);
});

test('plesk terminate keeps a customer that owns another subscription', function () {
    [$instance] = pleskLcActive();
    $log = pleskLcFake([
        'webspace.del' => [pleskLcOk('webspace', 'del', '<id>42</id>')],
        'webspace.get' => [pleskLcSubscription(55, 'other.example.test')],
    ]);

    pleskLcProvisioner()->terminate(pleskLcInfo($instance));

    expect(pleskLcOperations($log))->toBe(['webspace.del', 'webspace.get'])
        ->and(pleskLcClaim($instance)->only(['state', 'customer_id']))->toBe(['state' => 'terminated', 'customer_id' => null]);
});

test('plesk terminate failures keep the claim and never delete the customer blindly', function () {
    [$instance] = pleskLcActive();
    $log = pleskLcFake(['webspace.del' => [pleskLcError('webspace', 'del', 1013)]]);
    pleskLcRefused(fn () => pleskLcProvisioner()->terminate(pleskLcInfo($instance)));
    expect(pleskLcOperations($log))->toBe(['webspace.del'])
        ->and(pleskLcClaim($instance)->state)->toBe('active');

    pleskLcFake([
        'webspace.del' => [pleskLcOk('webspace', 'del', '<id>42</id>')],
        'webspace.get' => [pleskLcError('webspace', 'get', 1013)],
    ]);
    pleskLcRefused(fn () => pleskLcProvisioner()->terminate(pleskLcInfo($instance)));
    expect(pleskLcClaim($instance)->only(['state', 'customer_id']))->toBe(['state' => 'terminated', 'customer_id' => 7]);

    $retry = pleskLcFake([
        'webspace.get' => [pleskLcPacket('webspace', 'get', '')],
        'customer.del' => [pleskLcOk('customer', 'del', '<id>7</id>')],
    ]);
    pleskLcProvisioner()->terminate(pleskLcInfo($instance));
    expect(pleskLcOperations($retry))->toBe(['webspace.get', 'customer.del'])
        ->and(pleskLcClaim($instance)->customer_id)->toBeNull();
});

test('plesk terminate refuses unverified claims and closes an unclaimed service without network', function () {
    $instance = pleskLcInstance();
    pleskLcFake(['service-plan.get' => [pleskLcPlans('Starter')], 'customer.add' => [fn () => Http::failedConnection()]]);
    pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));
    $log = pleskLcFake([]);

    pleskLcRefused(fn () => pleskLcProvisioner()->terminate(pleskLcInfo($instance)));
    pleskLcProvisioner()->terminate(pleskLcInfo(pleskLcInstance()));

    expect($log->count())->toBe(0)
        ->and(pleskLcClaim($instance)->state)->toBe('unknown');
});

test('plesk change plan switches to the documented plan guid and verifies it afterwards', function () {
    [$instance] = pleskLcActive();
    $log = pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter', 'Business')],
        'webspace.switch-subscription' => [pleskLcOk('webspace', 'switch-subscription', '<filter-id>42</filter-id><id>42</id>')],
        'webspace.get' => [pleskLcSubscription(42, pleskLcDomain($instance), plan: 'Business')],
    ]);

    pleskLcProvisioner()->changePlan(pleskLcInfo($instance), ['provider_settings' => ['service_plan' => 'Business']]);

    expect(pleskLcOperations($log))->toBe(['service-plan.get', 'webspace.switch-subscription', 'webspace.get'])
        ->and($log[1]['body'])->toContain('<switch-subscription><filter><id>42</id></filter><plan-guid>guid-Business</plan-guid></switch-subscription>')
        ->and(pleskLcClaim($instance)->plan)->toBe('Business');
});

test('plesk change plan failures keep the recorded plan', function (array $script, array $operations) {
    [$instance] = pleskLcActive();
    $log = pleskLcFake($script);

    pleskLcRefused(fn () => pleskLcProvisioner()->changePlan(pleskLcInfo($instance), ['provider_settings' => ['service_plan' => 'Business']]));

    expect(pleskLcClaim($instance)->plan)->toBe('Starter')
        ->and(pleskLcOperations($log))->toBe($operations);
})->with([
    'plan missing' => [['service-plan.get' => [pleskLcPlans('Starter')]], ['service-plan.get']],
    'switch rejected' => [
        ['service-plan.get' => [pleskLcPlans('Starter', 'Business')], 'webspace.switch-subscription' => [pleskLcError('webspace', 'switch-subscription', 1023)]],
        ['service-plan.get', 'webspace.switch-subscription'],
    ],
    'not applied' => [
        [
            'service-plan.get' => [pleskLcPlans('Starter', 'Business')],
            'webspace.switch-subscription' => [pleskLcOk('webspace', 'switch-subscription', '<id>42</id>')],
            'webspace.get' => [pleskLcSubscription(42, 'x.example.test', plan: 'Starter')],
        ],
        ['service-plan.get', 'webspace.switch-subscription', 'webspace.get'],
    ],
]);

test('plesk sync maps documented subscription status values', function () {
    [$instance] = pleskLcActive();
    $domain = pleskLcDomain($instance);
    $log = pleskLcFake(['webspace.get' => [
        pleskLcSubscription(42, $domain, 16),
        pleskLcSubscription(42, $domain, 64),
        pleskLcSubscription(42, $domain, 0),
    ]]);

    expect(pleskLcProvisioner()->syncStatus(pleskLcInfo($instance))->status)->toBe('suspended')
        ->and(pleskLcClaim($instance)->state)->toBe('suspended')
        ->and(pleskLcProvisioner()->syncStatus(pleskLcInfo($instance))->status)->toBe('suspended');
    $info = pleskLcProvisioner()->syncStatus(pleskLcInfo($instance));

    expect($info->status)->toBe('active')
        ->and($info->externalRef)->toBe('42')
        ->and($info->meta['provider_mapping'])->toBe(['provider_id' => '42', 'domain' => $domain, 'plan' => 'Starter'])
        ->and(pleskLcClaim($instance)->state)->toBe('active')
        ->and($log[0]['body'])->toContain('<filter><id>42</id></filter><dataset><gen_info/><subscriptions/></dataset>');
});

test('plesk sync refuses missing, foreign, unknown or unsafe provider data', function (string|Closure $response) {
    [$instance] = pleskLcActive();
    pleskLcFake(['webspace.get' => [$response]]);

    pleskLcRefused(fn () => pleskLcProvisioner()->syncStatus(pleskLcInfo($instance)));

    expect(pleskLcClaim($instance)->state)->toBe('active');
})->with([
    'deleted' => [pleskLcError('webspace', 'get', 1013)],
    'foreign owner' => [pleskLcSubscription(42, 'x.example.test', ownerId: 99)],
    'backup status' => [pleskLcSubscription(42, 'x.example.test', 4)],
    'other id' => [pleskLcSubscription(43, 'x.example.test')],
    // Pest resolves closures in datasets, so the fake response closure is returned by one.
    'redirect' => [fn () => fn () => Http::response('', 302, ['Location' => 'https://evil.example.test/'])],
    'entity' => [str_replace(['<packet ', '<status>0</status>'], ['<!DOCTYPE packet [<!ENTITY x "0">]><packet ', '<status>&x;</status>'], pleskLcSubscription(42, 'x.example.test'))],
    'not xml' => ['{"status":"ok"}'],
]);

test('plesk lifecycle is refused without network when the endpoint is re-pointed', function () {
    [$instance] = pleskLcActive();
    pleskLcPointAt('https://other-plesk.example.test:8443');
    $log = pleskLcFake([]);

    foreach (['suspend', 'unsuspend', 'terminate', 'syncStatus', 'provision'] as $method) {
        pleskLcRefused(fn () => pleskLcProvisioner()->{$method}(pleskLcInfo($instance)));
    }
    pleskLcRefused(fn () => pleskLcProvisioner()->changePlan(pleskLcInfo($instance), ['provider_settings' => ['service_plan' => 'Business']]));

    expect($log->count())->toBe(0)
        ->and(pleskLcClaim($instance)->state)->toBe('active');
});

test('plesk hand written provider mapping without a claim row is refused without network', function () {
    $instance = pleskLcInstance(['provider_mapping' => ['provider_id' => '42']]);
    $instance->forceFill(['external_ref' => '42'])->save();
    $log = pleskLcFake([]);

    foreach (['provision', 'suspend', 'unsuspend', 'terminate', 'syncStatus'] as $method) {
        pleskLcRefused(fn () => pleskLcProvisioner()->{$method}(pleskLcInfo($instance)));
    }

    expect($log->count())->toBe(0)
        ->and(pleskLcClaim($instance))->toBeNull();
});

test('plesk stale revision between read and write never overwrites the row', function () {
    [$instance, $claim] = pleskLcActive();
    pleskLcFake(['webspace.set' => [function () use ($claim) {
        DB::table('plesk_accounts')->where('id', $claim->id)->update(['revision' => 9]);

        return Http::response(pleskLcOk('webspace', 'set', '<id>42</id>'), 200);
    }]]);

    pleskLcRefused(fn () => pleskLcProvisioner()->suspend(pleskLcInfo($instance)));

    expect(pleskLcClaim($instance)->only(['state', 'revision']))->toBe(['state' => 'active', 'revision' => 9]);
});

test('plesk never leaks the api key or generated passwords', function () {
    $instance = pleskLcInstance();
    $log = pleskLcFake([
        'service-plan.get' => [pleskLcPlans('Starter')],
        'customer.add' => [pleskLcOk('customer', 'add', '<id>7</id>')],
        'webspace.add' => [pleskLcError('webspace', 'add', 2300)],
    ]);

    $exception = pleskLcRefused(fn () => pleskLcProvisioner()->provision(pleskLcInfo($instance)));
    preg_match('#<passwd>([^<]+)</passwd>#', $log[1]['body'], $customerPassword);
    preg_match('#<name>ftp_password</name><value>([^<]+)</value>#', $log[2]['body'], $ftpPassword);
    $visible = json_encode([$exception->errors(), $exception->getMessage(), pleskLcClaim($instance)->getAttributes(), $instance->fresh()->getAttributes()]);

    expect($customerPassword[1] ?? '')->not->toBe('')
        ->and($ftpPassword[1] ?? '')->not->toBe('')
        ->and($customerPassword[1])->not->toBe($ftpPassword[1])
        ->and($visible)->not->toContain(PLESK_LC_TOKEN)
        ->not->toContain($customerPassword[1])
        ->not->toContain($ftpPassword[1]);
});

test('plesk panel shows the claimed subscription without network or credentials', function () {
    [$instance] = pleskLcActive();
    $log = pleskLcFake([]);

    $panel = pleskLcProvisioner()->panel(pleskLcInfo($instance));

    $values = array_column($panel->fields, 'value');
    expect($log->count())->toBe(0)
        ->and($values)->toContain(pleskLcLogin($instance->id), 'Starter', pleskLcDomain($instance))
        ->and(json_encode($panel->fields))->not->toContain(PLESK_LC_TOKEN)
        ->and(pleskLcProvisioner()->panel(pleskLcInfo(pleskLcInstance())))->toBeNull();
});

test('plesk health uses the documented read-only get_protos packet and checks the status', function () {
    $log = pleskLcFake(['server.get_protos' => [
        pleskLcOk('server', 'get_protos', '<protos><proto>1.6.9.1</proto></protos>'),
        pleskLcSystemError(1001),
    ]]);
    $settings = ['api_url' => PLESK_LC_URL, 'api_token' => PLESK_LC_TOKEN];

    expect(pleskLcProvisioner()->testServer($settings)->ok)->toBeTrue()
        ->and(pleskLcProvisioner()->testServer($settings)->ok)->toBeFalse()
        ->and(pleskLcProvisioner()->testServer(['api_url' => 'https://u:p@plesk.example.test'] + $settings)->ok)->toBeFalse()
        ->and($log->count())->toBe(2)
        ->and($log[0]['body'])->toContain('<packet><server><get_protos/></server></packet>')
        ->and($log[0]['key'])->toBe(PLESK_LC_TOKEN);
});
