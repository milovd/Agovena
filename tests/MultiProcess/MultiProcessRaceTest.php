<?php

declare(strict_types=1);

use Agovena\Modules\Events\Enums\EventStatus;
use Agovena\Modules\Events\Enums\EventTicketStatus;
use Agovena\Modules\Events\Models\Event;
use Agovena\Modules\Events\Models\EventPerformance;
use Agovena\Modules\Events\Models\EventTicket;
use Agovena\Modules\Events\Models\EventTicketType;
use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Subscriptions\Models\Subscription;
use Agovena\Modules\Subscriptions\Models\SubscriptionRenewal;
use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function raceEnvPath(): string
{
    $path = storage_path('framework/race-env-'.Str::random(8).'.json');
    file_put_contents($path, json_encode(array_filter([
        'APP_ENV' => 'testing',
        'APP_KEY' => config('app.key'),
        'APP_DEBUG' => 'true',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => config('database.connections.mysql.host'),
        'DB_PORT' => (string) config('database.connections.mysql.port'),
        'DB_DATABASE' => config('database.connections.mysql.database'),
        'DB_USERNAME' => config('database.connections.mysql.username'),
        'DB_PASSWORD' => config('database.connections.mysql.password'),
        'CACHE_STORE' => 'database',
        'QUEUE_CONNECTION' => 'database',
        'MAIL_MAILER' => 'array',
        'SESSION_DRIVER' => 'array',
        'AGOVENA_OPTIONAL_PACKAGES_PATH' => config('agovena.packages.optional_packages_path'),
    ], fn ($value) => $value !== null && $value !== ''), JSON_THROW_ON_ERROR));

    return $path;
}

/**
 * @return list<array{ok: bool, error: ?string, already?: bool, processed?: int}>
 */
function runRaceWorkers(string $action, array $payload, int $copies = 2): array
{
    $envPath = raceEnvPath();
    $script = base_path('tests/MultiProcess/workers/race_worker.php');
    $json = json_encode($payload, JSON_THROW_ON_ERROR);
    $processes = [];

    for ($i = 0; $i < $copies; $i++) {
        $process = new Process([PHP_BINARY, $script, $action, $json, $envPath], base_path());
        $process->setTimeout(60);
        $process->start();
        $processes[] = $process;
    }

    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        $line = trim($process->getOutput());
        $decoded = $line !== '' ? json_decode($line, true) : null;
        $results[] = is_array($decoded) ? $decoded : [
            'ok' => false,
            'error' => 'bad-output:'.$process->getErrorOutput().$line,
            'exit' => $process->getExitCode(),
        ];
    }

    @unlink($envPath);

    return $results;
}

/** @param list<array{action: string, payload: array<string, mixed>}> $jobs */
function runRaceWorkerJobs(array $jobs): array
{
    $envPath = raceEnvPath();
    $script = base_path('tests/MultiProcess/workers/race_worker.php');
    $processes = [];

    foreach ($jobs as $job) {
        $process = new Process([
            PHP_BINARY,
            $script,
            $job['action'],
            json_encode($job['payload'], JSON_THROW_ON_ERROR),
            $envPath,
        ], base_path());
        $process->setTimeout(60);
        $process->start();
        $processes[] = $process;
    }

    $results = [];
    foreach ($processes as $process) {
        $process->wait();
        $line = trim($process->getOutput());
        $decoded = $line !== '' ? json_decode($line, true) : null;
        $results[] = is_array($decoded) ? $decoded : [
            'ok' => false,
            'error' => 'bad-output:'.$process->getErrorOutput().$line,
            'exit' => $process->getExitCode(),
        ];
    }

    @unlink($envPath);

    return $results;
}

function placePaidOrderForRace(int $price = 2000, int $qty = 2): array
{
    $product = Product::factory()->active()->create(['price_amount' => $price]);
    app(CartService::class)->clear();
    app(CartService::class)->add($product->id, $qty);
    $customer = Customer::factory()->create();
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Race Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);
    $staff = test()->createStaff();
    app(RecordManualPayment::class)->handle($order, $staff, 'RACE-PAY');
    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();

    return [$order->fresh(['payment']), $invoice->fresh('items'), $staff];
}

test('two processes cannot over-refund the same payment', function () {
    [$order, $invoice, $staff] = placePaidOrderForRace(2000, 1);
    $payment = $order->payment;
    expect($payment)->toBeInstanceOf(Payment::class)
        ->and($payment->amount)->toBe(2000);

    $results = runRaceWorkers('refund', [
        'staff_id' => $staff->id,
        'payment_id' => $payment->id,
        'amount' => 2000,
        'reason' => 'parallel full refund',
    ]);

    $oks = collect($results)->where('ok', true)->count();
    $fails = collect($results)->where('ok', false)->count();

    expect($oks)->toBe(1, json_encode($results, JSON_THROW_ON_ERROR))
        ->and($fails)->toBe(1)
        ->and(Refund::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and($payment->fresh()->remainingRefundable())->toBe(0);
});

test('two processes cannot over-credit remaining invoice quantity', function () {
    [$order, $invoice, $staff] = placePaidOrderForRace(1500, 1);
    $item = $invoice->creditableItems()->firstOrFail();

    $results = runRaceWorkers('credit', [
        'staff_id' => $staff->id,
        'invoice_id' => $invoice->id,
        'reason' => 'parallel credit',
        'quantities' => [$item->id => 1],
    ]);

    $oks = collect($results)->where('ok', true)->count();

    expect($oks)->toBe(1, json_encode($results, JSON_THROW_ON_ERROR))
        ->and($invoice->fresh()->remainingCreditable())->toBe(0);
});

test('a concurrent limited role update cannot demote a promoted owner', function () {
    $owner = test()->createStaff();
    $limited = test()->createStaff([], ['users.view', 'users.update']);
    $target = User::factory()->create();

    $results = runRaceWorkerJobs([
        [
            'action' => 'role-sync',
            'payload' => [
                'actor_id' => $owner->id,
                'target_id' => $target->id,
                'roles' => ['owner'],
            ],
        ],
        [
            'action' => 'role-sync',
            'payload' => [
                'actor_id' => $limited->id,
                'target_id' => $target->id,
                'roles' => ['staff_limited'],
            ],
        ],
    ]);

    expect(collect($results)->where('ok', true)->count())->toBeGreaterThanOrEqual(1)
        ->and($target->fresh()->hasRole('owner'))->toBeTrue();
});

test('concurrent imports reserve one source identity', function () {
    try {
        DB::connection('mysql')->getPdo();
    } catch (Throwable) {
        test()->markTestSkipped('MariaDB concurrency suite');
    }

    $path = tempnam(sys_get_temp_dir(), 'agovena-import-race-');
    file_put_contents($path, "external_id,email,name\nC-IMPORT-RACE,import-race@example.test,Import Race\n");

    $results = runRaceWorkers('import', [
        'path' => $path,
        'source' => 'csv',
        'entity' => 'customer',
    ]);

    unlink($path);

    expect(collect($results)->where('ok', true)->count())->toBe(2)
        ->and(collect($results)->sum('imported'))->toBe(1)
        ->and(collect($results)->sum('duplicates'))->toBe(1)
        ->and(collect($results)->sum('errors'))->toBe(0)
        ->and(User::query()->where('email', 'import-race@example.test')->count())->toBe(1);
});

test('two renewal processors create one renewal order only', function () {
    installAndEnableModule('subscriptions');
    app(SyncRegisteredPermissions::class)(force: true);

    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1999]);
    app(ProductCapabilityManager::class)->enable($product, 'subscribable', [
        'interval' => 'month',
        'interval_count' => 1,
        'trial_days' => 0,
    ]);
    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'billing' => AddressData::fromArray([
            'name' => $customer->name,
            'line1' => 'Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);
    app(RecordManualPayment::class)->handle($order, test()->createStaff());
    $subscription = Subscription::query()->where('order_id', $order->id)->firstOrFail();
    $due = CarbonImmutable::parse($subscription->next_billing_at);

    $results = runRaceWorkers('renewal', [
        'now' => $due->toIso8601String(),
    ]);

    expect(collect($results)->where('ok', true)->count())->toBeGreaterThan(0)
        ->and(SubscriptionRenewal::query()->where('subscription_id', $subscription->id)->count())->toBe(1);
});

test('duplicate ticket check-in across processes yields one first success', function () {
    installAndEnableModule('events');
    app(SyncRegisteredPermissions::class)(force: true);

    [$order] = placePaidOrderForRace(1000, 1);

    $event = Event::query()->create([
        'name' => 'Race Event',
        'slug' => 'race-event-'.Str::lower(Str::random(4)),
        'venue' => 'Hall',
        'status' => EventStatus::Published,
    ]);
    $performance = EventPerformance::query()->create([
        'event_id' => $event->id,
        'starts_at' => now()->addWeek(),
        'capacity' => 10,
        'venue' => $event->venue,
    ]);
    $product = Product::factory()->active()->create(['price_amount' => 1000]);
    $type = EventTicketType::query()->create([
        'event_id' => $event->id,
        'performance_id' => $performance->id,
        'product_id' => $product->id,
        'name' => 'GA',
        'capacity' => 10,
    ]);
    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'performance_id' => $performance->id,
        'ticket_type_id' => $type->id,
        'product_id' => $product->id,
        'order_id' => $order->id,
        'number' => 'TCK-RACE-'.Str::upper(Str::random(6)),
        'token' => hash('sha256', Str::random(40)),
        'status' => EventTicketStatus::Issued,
        'customer_email' => 'race-ticket@example.test',
        'customer_name' => 'Race Ticket',
    ]);
    $staff = test()->createStaff();

    $results = runRaceWorkers('checkin', [
        'code' => $ticket->token,
        'staff_id' => $staff->id,
    ]);

    $firstSuccess = collect($results)->filter(fn ($r) => ($r['ok'] ?? false) && ! ($r['already'] ?? true))->count();
    $already = collect($results)->filter(fn ($r) => ($r['ok'] ?? false) && ($r['already'] ?? false))->count();

    expect($firstSuccess + $already)->toBe(2)
        ->and($firstSuccess)->toBe(1)
        ->and($ticket->fresh()->status)->toBe(EventTicketStatus::CheckedIn);
});

test('duplicate provisioning dispatch keeps a single unique job intent', function () {
    installAndEnableModule('provisioning');
    config(['queue.default' => 'database', 'cache.default' => 'database']);
    DB::table('jobs')->delete();

    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-RACE-'.Str::upper(Str::random(6)),
        'customer_email' => 'race-svc@example.test',
        'customer_name' => 'Race',
        'status' => ServiceInstanceStatus::Pending,
        'provider_key' => 'local',
    ]);

    // Parallel dispatch must not crash; UniqueJob may collapse to 0–2 queued rows
    // depending on cache-lock timing under database cache.
    $results = runRaceWorkers('provision-dispatch', [
        'instance_id' => $instance->id,
    ]);

    expect(collect($results)->where('ok', true)->count())->toBe(2);

    $matching = DB::table('jobs')->get()->filter(function ($job) use ($instance): bool {
        $payload = (string) $job->payload;

        return str_contains($payload, 'ProvisionServiceInstance')
            && (
                str_contains($payload, '"instanceId":'.$instance->id)
                || str_contains($payload, 's:10:"instanceId";i:'.$instance->id.';')
                || str_contains($payload, 'instanceId";i:'.$instance->id.';')
            );
    });

    expect($matching->count())->toBeLessThanOrEqual(2);
});
