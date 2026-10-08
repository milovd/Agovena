<?php

declare(strict_types=1);

use App\Agovena\Maintenance\MaintenanceMode;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Http\Middleware\EnforceStorefrontMaintenance;
use App\Models\AuditLog;
use App\Models\PaymentWebhookEvent;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\Support\CreatesStaff;
use Tests\Support\FakeWebhookGateway;

uses(CreatesStaff::class);

function maintenanceSnapshot(TestResponse $page, string $needle): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', (string) $page->getContent(), $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5);
        if (str_contains((string) (json_decode($snapshot, true)['memo']['name'] ?? ''), $needle)) {
            return $snapshot;
        }
    }

    throw new RuntimeException("Component [{$needle}] was not rendered.");
}

function maintenanceLivewireRefresh(string $snapshot): TestResponse
{
    return test()->withHeaders(['X-Livewire' => '1'])->postJson(app(HandleRequests::class)->getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]],
        ]],
    ]);
}

test('the storefront is open while maintenance mode is off', function () {
    $this->get('/')->assertOk();
    expect(app(MaintenanceMode::class)->state()->enabled)->toBeFalse();
});

test('storefront pages return a themed 503 with retry-after, noindex and the merchant message', function () {
    $this->freezeTime();
    app(MaintenanceMode::class)->enable('We are moving <warehouses> & will be back soon.', now()->addHours(2));

    $response = $this->get('/');

    $response->assertStatus(503)
        ->assertHeader('Retry-After', '7200')
        ->assertSee('data-error-status="maintenance"', false)
        ->assertSee('We are moving &lt;warehouses&gt; &amp; will be back soon.', false)
        ->assertDontSee('<warehouses>', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee(__('errors.maintenance.heading'));

    expect((string) $response->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

test('cart, checkout, product, content and account pages are blocked for visitors and customers', function () {
    $product = Product::factory()->active()->create(['slug' => 'maintenance-product']);
    app(MaintenanceMode::class)->enable(null, null);

    foreach (['/cart', '/checkout', '/products/'.$product->slug, '/categories', '/register', '/search/suggest?q=a'] as $path) {
        $this->get($path)->assertStatus(503)->assertHeader('Retry-After');
    }

    $customer = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($customer)->get('/account')->assertStatus(503);
    $this->actingAs($customer)->get('/account/orders')->assertStatus(503);
});

test('a retry-after default is used without an expected end time', function () {
    app(MaintenanceMode::class)->enable(null, null);

    $retryAfter = (int) $this->get('/')->assertStatus(503)->headers->get('Retry-After');

    expect($retryAfter)->toBe(MaintenanceMode::DEFAULT_RETRY_AFTER_SECONDS);
});

test('the public storefront api returns a json 503 error', function () {
    $this->freezeTime();
    app(MaintenanceMode::class)->enable('Inventory count', now()->addMinutes(30));

    $this->getJson('/api/v1/products')
        ->assertStatus(503)
        ->assertHeader('Retry-After', '1800')
        ->assertJson([
            'code' => 'maintenance',
            'message' => 'Inventory count',
        ]);

    $this->getJson('/api/v1/cart')->assertStatus(503)->assertJsonPath('code', 'maintenance');
});

test('the admin, staff sign-in flows and the health endpoint stay reachable', function () {
    $staff = $this->createStaff();
    app(MaintenanceMode::class)->enable('Closed', null);

    $this->get('/login')->assertOk();
    $this->get('/forgot-password')->assertOk();
    $this->get('/reset-password/some-token?email=staff@example.test')->assertOk();
    $this->get('/up')->assertOk();
    $this->get('/robots.txt')->assertOk();

    $this->actingAs($staff)->get('/admin')->assertOk();
    $this->actingAs($staff)->get('/admin/maintenance')->assertOk();
});

test('livewire updates for storefront components are blocked while sign-in components keep working', function () {
    $product = Product::factory()->active()->create(['slug' => 'livewire-maintenance']);
    $cartSnapshot = maintenanceSnapshot($this->get('/cart')->assertOk(), 'cart-page');
    $loginSnapshot = maintenanceSnapshot($this->get('/login')->assertOk(), 'login');

    app(MaintenanceMode::class)->enable(null, null);

    maintenanceLivewireRefresh($cartSnapshot)->assertStatus(503);
    maintenanceLivewireRefresh($loginSnapshot)->assertOk();
    expect($product->exists)->toBeTrue();
});

test('a payment webhook still reaches its handler during maintenance', function () {
    app(PaymentGatewayRegistry::class)->register(new FakeWebhookGateway);
    app(MaintenanceMode::class)->enable('Closed', null);

    $this->postJson(route('webhooks.payments', ['gateway' => 'fake-webhook']), [
        'event_id' => 'evt-maintenance-1',
        'status' => 'paid',
    ], [
        'X-Webhook-Secret' => 'test-secret',
    ])->assertOk()->assertJson(['ok' => true]);

    expect(PaymentWebhookEvent::query()->where('gateway_id', 'fake-webhook')->count())->toBe(1);
});

test('every webhook and callback route stays outside the maintenance block', function () {
    app(MaintenanceMode::class)->enable(null, null);

    $blocked = [];
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $name = (string) $route->getName();
        $uri = $route->uri();
        if (! str_contains($name, 'webhook') && ! str_contains($name, 'callback') && ! str_contains($uri, 'webhook') && ! str_contains($uri, 'callback')) {
            continue;
        }

        $request = Request::create('/'.ltrim($uri, '/'), $route->methods()[0]);
        $request->setRouteResolver(static fn () => $route);

        if (! app(EnforceStorefrontMaintenance::class)->allowsDuringMaintenance($request)) {
            $blocked[] = $name !== '' ? $name : $uri;
        }
    }

    expect($blocked)->toBe([]);
});

test('staff with admin access browse the storefront normally and see the maintenance banner', function () {
    $owner = $this->createStaff();
    app(MaintenanceMode::class)->enable('Closed', null);

    $this->actingAs($owner)->get('/')
        ->assertOk()
        ->assertSee('data-maintenance-notice', false)
        ->assertSee(__('storefront.maintenance_notice.text'))
        ->assertSee(route('admin.maintenance'), false);

    $this->actingAs($owner)->get('/cart')->assertOk()->assertSee('data-maintenance-notice', false);
});

test('staff without the maintenance permission see the banner without the manage link', function () {
    $staff = $this->createStaff(permissions: ['orders.view']);
    app(MaintenanceMode::class)->enable(null, null);

    $this->actingAs($staff)->get('/')
        ->assertOk()
        ->assertSee('data-maintenance-notice', false)
        ->assertDontSee(route('admin.maintenance'), false);
});

test('the maintenance banner is hidden while maintenance mode is off', function () {
    $owner = $this->createStaff();

    $this->actingAs($owner)->get('/')->assertOk()->assertDontSee('data-maintenance-notice', false);
});

test('maintenance state survives a cache flush', function () {
    app(MaintenanceMode::class)->enable('Persisted', null);
    Cache::flush();

    expect(app(MaintenanceMode::class)->state()->enabled)->toBeTrue()
        ->and(app(MaintenanceMode::class)->state()->message)->toBe('Persisted');
    $this->get('/')->assertStatus(503);
});

test('the maintenance state is read from the cache without database queries once warm', function () {
    $maintenance = app(MaintenanceMode::class);
    $settingsQueries = static function (callable $callback): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'settings'))->count();
        DB::disableQueryLog();

        return $count;
    };

    $maintenance->state();
    expect($settingsQueries(fn () => $maintenance->state()))->toBe(0);

    $maintenance->enable('Cached', null);
    expect($settingsQueries(fn () => $maintenance->state()))->toBe(1)
        ->and($settingsQueries(fn () => $maintenance->state()))->toBe(0)
        ->and($maintenance->state()->message)->toBe('Cached');
});

test('an unreadable maintenance setting fails open instead of crashing', function () {
    app(MaintenanceMode::class)->enable('Closed', null);
    Cache::flush();
    Schema::rename('settings', 'settings_unavailable');

    try {
        expect(app(MaintenanceMode::class)->state()->enabled)->toBeFalse();
    } finally {
        Schema::rename('settings_unavailable', 'settings');
    }
});

test('the recovery command turns maintenance on, reports status and turns it off', function () {
    $this->artisan('agovena:maintenance', ['action' => 'on', '--message' => 'Upgrading the shop'])
        ->expectsOutputToContain('Storefront maintenance: on')
        ->assertSuccessful();

    $state = app(MaintenanceMode::class)->state();
    expect($state->enabled)->toBeTrue()->and($state->message)->toBe('Upgrading the shop');
    $this->get('/')->assertStatus(503)->assertSee('Upgrading the shop');

    $this->artisan('agovena:maintenance', ['action' => 'status'])
        ->expectsOutputToContain('Upgrading the shop')
        ->assertSuccessful();

    $this->artisan('agovena:maintenance', ['action' => 'off'])->assertSuccessful();
    expect(app(MaintenanceMode::class)->state()->enabled)->toBeFalse();
    $this->get('/')->assertOk();

    $actions = AuditLog::query()->where('action', 'like', 'maintenance.%')->orderBy('id')->get();
    expect($actions->pluck('action')->all())->toBe(['maintenance.enabled', 'maintenance.disabled'])
        ->and($actions->pluck('actor_type')->unique()->all())->toBe(['system']);
});

test('the recovery command rejects unknown actions and too long messages', function () {
    $this->artisan('agovena:maintenance', ['action' => 'maybe'])->assertFailed();
    $this->artisan('agovena:maintenance', ['action' => 'on', '--message' => str_repeat('a', MaintenanceMode::MESSAGE_MAX_LENGTH + 1)])->assertFailed();

    expect(app(MaintenanceMode::class)->state()->enabled)->toBeFalse();
});

test('the recovery command sets an expected end time that drives retry-after', function () {
    $this->travelTo(now()->startOfMinute());
    $until = now()->addHour()->timezone(config('app.timezone'))->format('Y-m-d H:i');

    $this->artisan('agovena:maintenance', ['action' => 'on', '--until' => $until])->assertSuccessful();

    $this->get('/')->assertStatus(503)->assertHeader('Retry-After', '3600');

    $this->artisan('agovena:maintenance', ['action' => 'on', '--until' => now()->subDay()->format('Y-m-d H:i')])->assertFailed();
    $this->artisan('agovena:maintenance', ['action' => 'on', '--until' => 'not a date'])->assertFailed();
});

test('the recovery command reads the stored state instead of a stale cache entry', function () {
    app(MaintenanceMode::class)->enable(null, null);
    Setting::query()->where('group', MaintenanceMode::SETTING_GROUP)->where('key', MaintenanceMode::SETTING_KEY)->update([
        'value' => json_encode(['enabled' => false]),
    ]);

    $this->artisan('agovena:maintenance', ['action' => 'status'])
        ->expectsOutputToContain('Storefront maintenance: off')
        ->assertSuccessful();

    $this->get('/')->assertOk();
});
