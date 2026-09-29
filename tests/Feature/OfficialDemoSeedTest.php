<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('seeds the official demo catalog and customer journeys without loading accounts', function (): void {
    $exitCode = Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]);

    expect($exitCode)->toBe(0, Artisan::output())
        ->and(Product::query()->pluck('slug')->all())->toEqualCanonicalizing([
            'minecraft-survival-server',
            'domain-registration-and-dns-management',
            'agovena-essential-tee',
            'python-automation-starter-kit',
            'agovena-pro-license',
            'agovena-launch-night',
        ])
        ->and(Category::query()->whereNull('parent_id')->pluck('slug')->all())->toEqualCanonicalizing([
            'provisioning',
            'physical-products',
            'downloadable-products',
            'digital-products',
            'events',
        ])
        ->and(Category::query()->where('slug', 'provisioning')->first()->children()->pluck('slug')->all())
        ->toEqualCanonicalizing(['game-hosting', 'domain-services'])
        ->and(Order::query()->where('status', 'paid')->count())->toBe(6)
        ->and(Order::query()->where('status', 'cancelled')->count())->toBe(1)
        ->and(Order::query()->where('status', 'pending')->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(8)
        ->and(Product::query()->where('slug', 'agovena-essential-tee')->value('name'))->toBe('Agovena Merch')
        ->and(Product::query()->where('slug', 'minecraft-survival-server')->value('description'))
        ->toContain('NOT AN OFFICIAL MINECRAFT SERVICE');

    expect(Schema::hasTable('service_instances'))->toBeTrue()
        ->and(Schema::hasTable('domain_registrations'))->toBeTrue()
        ->and(Schema::hasTable('digital_entitlements'))->toBeTrue()
        ->and(Schema::hasTable('digital_secret_deliveries'))->toBeTrue()
        ->and(Schema::hasTable('event_tickets'))->toBeTrue()
        ->and(DB::table('product_capabilities')->count())->toBe(5)
        ->and(DB::table('service_instances')->where('provider_key', 'pterodactyl')->count())->toBe(1)
        ->and(DB::table('domain_registrations')->where('domain_name', 'demo.agovena.test')->where('status', 'active')->count())->toBe(1)
        ->and(DB::table('digital_entitlements')->count())->toBe(1)
        ->and(DB::table('digital_secret_deliveries')->where('status', 'delivered')->count())->toBe(1)
        ->and(DB::table('event_tickets')->where('status', 'issued')->count())->toBe(1)
        ->and(DB::table('shipments')->where('tracking_number', 'DEMO-TRACK-0001')->count())->toBe(1)
        ->and(DB::table('payments')->where('status', 'failed')->count())->toBe(1)
        ->and(DB::table('payments')->where('status', 'pending')->count())->toBe(1)
        ->and(DB::table('agovena_modules')->whereIn('module_id', ['provisioning', 'domains', 'downloads', 'digital-delivery', 'events'])->where('enabled', true)->count())->toBe(5)
        ->and(DB::table('agovena_extensions')->whereIn('extension_id', ['pterodactyl', 'cloudflare-domain'])->where('enabled', true)->count())->toBe(2);
});

it('does not load demo identities through normal database seeding', function (): void {
    Artisan::call('db:seed');

    expect(User::query()->whereIn('email', ['demo@agovena.com', 'admin@agovena.com', 'test@example.com'])->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0);
});

it('only replaces demo-owned records during a forced reseed', function (): void {
    $existingUser = User::factory()->create(['email' => 'existing-history@example.test']);
    $existingCustomer = $existingUser->ensureCustomer();
    $existingProduct = Product::factory()->create(['slug' => 'existing-history-product']);
    $existingOrder = Order::factory()->create([
        'number' => 'AGO-HISTORY-0001',
        'customer_id' => $existingCustomer->id,
        'customer_name' => $existingCustomer->name,
        'customer_email' => $existingCustomer->email,
    ]);

    $exitCode = Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]);

    expect($exitCode)->toBe(0, Artisan::output())
        ->and(User::query()->whereKey($existingUser->id)->exists())->toBeTrue()
        ->and(Customer::query()->whereKey($existingCustomer->id)->exists())->toBeTrue()
        ->and(Product::query()->whereKey($existingProduct->id)->exists())->toBeTrue()
        ->and(Order::query()->whereKey($existingOrder->id)->exists())->toBeTrue();
});

it('does not clear existing data when account credentials are missing', function (): void {
    Product::factory()->create(['slug' => 'existing-local-record']);

    $exitCode = Artisan::call('agovena:seed-demo', ['--force' => true]);

    expect($exitCode)->toBe(1)
        ->and(Product::query()->where('slug', 'existing-local-record')->exists())->toBeTrue();
});
