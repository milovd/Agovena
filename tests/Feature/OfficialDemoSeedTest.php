<?php

declare(strict_types=1);

use App\Agovena\Demo\DemoAccountPasswords;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Packages\MonorepoCheckout;
use App\Agovena\Packages\MonorepoPackageMap;
use App\Agovena\Physical\Enums\ShippingMethodType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FakeMonorepoCheckout;

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
        ->and(Product::query()->where('slug', 'agovena-launch-night')->value('name'))->toBe('Concert Demo')
        ->and(Product::query()->where('slug', 'agovena-launch-night')->value('sku'))->toBe('AGV-EVENT-DEMO')
        ->and(Product::query()->where('slug', 'agovena-launch-night')->value('subtitle'))->toContain('fictional')
        ->and(DB::table('events')->where('slug', 'agovena-launch-night')->value('name'))->toBe('Concert Demo')
        ->and(DB::table('events')->where('slug', 'agovena-launch-night')->value('venue'))->toBe('Demo Venue')
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
        ->and(DB::table('shipping_methods')->where('code', 'demo-parcel')->value('type'))->toBe(ShippingMethodType::Zone->value)
        ->and(json_decode(DB::table('shipping_methods')->where('code', 'demo-parcel')->value('config'), true))->toBe(['amount' => 499])
        ->and(DB::table('agovena_modules')->whereIn('module_id', ['provisioning', 'domains', 'downloads', 'digital-delivery', 'events'])->where('enabled', true)->count())->toBe(5)
        ->and(DB::table('agovena_extensions')->whereIn('extension_id', ['pterodactyl', 'cloudflare-domain'])->where('enabled', true)->count())->toBe(2);

    $memoryOptionId = DB::table('product_options')
        ->where('product_id', Product::query()->where('slug', 'minecraft-survival-server')->value('id'))
        ->where('key', 'memory')
        ->value('id');

    expect(DB::table('product_option_choices')
        ->where('product_option_id', $memoryOptionId)
        ->orderBy('value')
        ->pluck('price_adjustment_amount', 'value')
        ->all())->toBe([
            '16gb' => 1000,
            '4gb' => 0,
            '8gb' => 300,
        ]);
});

it('can replace the demo catalog during a forced reseed', function (): void {
    expect(Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]))->toBe(0, Artisan::output());

    expect(Artisan::call('agovena:seed-demo', [
        '--force' => true,
        '--skip-accounts' => true,
    ]))->toBe(0, Artisan::output())
        ->and(Product::query()->where('slug', 'minecraft-survival-server')->count())->toBe(1);

    $reseededMinecraftProductId = Product::query()->where('slug', 'minecraft-survival-server')->value('id');

    expect(DB::table('product_options')->where('product_id', $reseededMinecraftProductId)->count())->toBe(2);
});

it('bootstraps missing demo packages from the configured monorepo', function (): void {
    $optionalRoot = optionalPackagesRoot();
    $configuredPath = config('agovena.packages.optional_packages_path');
    $configuredRepository = config('agovena.packages.monorepo.repository');
    $configuredEnvironment = app()->environment();
    $packagesRoot = storage_path('app/packages');
    $fake = new FakeMonorepoCheckout(app(MonorepoPackageMap::class));
    $fake->map('https://github.com/milovd/optional-packages', $optionalRoot);

    File::deleteDirectory($packagesRoot);
    config([
        'agovena.packages.optional_packages_path' => base_path('missing-optional-packages'),
        'agovena.packages.monorepo.repository' => 'https://github.com/milovd/optional-packages',
    ]);
    app()->instance(MonorepoCheckout::class, $fake);
    app(ModuleManager::class)->refresh();
    app(ExtensionManager::class)->refresh();

    try {
        $exitCode = Artisan::call('agovena:seed-demo', [
            '--force' => true,
            '--skip-accounts' => true,
        ]);

        expect($exitCode)->toBe(0, Artisan::output())
            ->and($fake->resolved)->toHaveCount(7)
            ->and(File::exists(storage_path('app/packages/modules/provisioning/module.json')))->toBeTrue()
            ->and(File::exists(storage_path('app/packages/extensions/pterodactyl/extension.json')))->toBeTrue();
    } finally {
        config([
            'agovena.packages.optional_packages_path' => $configuredPath,
            'agovena.packages.monorepo.repository' => $configuredRepository,
        ]);
        app()['env'] = $configuredEnvironment;
        app()->forgetInstance(MonorepoCheckout::class);
        File::deleteDirectory($packagesRoot);
        app(ModuleManager::class)->refresh();
        app(ExtensionManager::class)->refresh();
    }
});

it('seeds a demo environment without enabling experimental provider extensions', function (): void {
    $optionalRoot = optionalPackagesRoot();
    $configuredPath = config('agovena.packages.optional_packages_path');
    $configuredRepository = config('agovena.packages.monorepo.repository');
    $configuredEnvironment = app()->environment();
    $packagesRoot = storage_path('app/packages');
    $fake = new FakeMonorepoCheckout(app(MonorepoPackageMap::class));
    $fake->map('https://github.com/milovd/optional-packages', $optionalRoot);

    File::deleteDirectory($packagesRoot);
    config([
        'agovena.packages.optional_packages_path' => base_path('missing-optional-packages'),
        'agovena.packages.monorepo.repository' => 'https://github.com/milovd/optional-packages',
    ]);
    app()['env'] = 'demo';
    app()->instance(MonorepoCheckout::class, $fake);
    app(ModuleManager::class)->refresh();
    app(ExtensionManager::class)->refresh();

    try {
        $exitCode = Artisan::call('agovena:seed-demo', [
            '--force' => true,
            '--skip-accounts' => true,
        ]);

        expect($exitCode)->toBe(0, Artisan::output())
            ->and($fake->resolved)->toHaveCount(5)
            ->and(File::exists(storage_path('app/packages/extensions/pterodactyl/extension.json')))->toBeFalse()
            ->and(File::exists(storage_path('app/packages/extensions/cloudflare-domain/extension.json')))->toBeFalse()
            ->and(DB::table('agovena_extensions')->where('enabled', true)->count())->toBe(0)
            ->and(DB::table('agovena_modules')->whereIn('module_id', ['provisioning', 'domains', 'downloads', 'digital-delivery', 'events'])->where('enabled', true)->count())->toBe(5);
    } finally {
        config([
            'agovena.packages.optional_packages_path' => $configuredPath,
            'agovena.packages.monorepo.repository' => $configuredRepository,
        ]);
        app()['env'] = $configuredEnvironment;
        app()->forgetInstance(MonorepoCheckout::class);
        File::deleteDirectory($packagesRoot);
        app(ModuleManager::class)->refresh();
        app(ExtensionManager::class)->refresh();
    }
});

it('rejects a mismatched manifest during demo package bootstrap', function (): void {
    $optionalRoot = optionalPackagesRoot();
    $configuredPath = config('agovena.packages.optional_packages_path');
    $configuredRepository = config('agovena.packages.monorepo.repository');
    $configuredPackages = config('agovena.packages.monorepo.packages');
    $packagesRoot = storage_path('app/packages');
    $fake = new FakeMonorepoCheckout(app(MonorepoPackageMap::class));
    $fake->map('https://github.com/milovd/optional-packages', $optionalRoot);
    $packageMap = $configuredPackages;
    $packageMap['provisioning']['path'] = 'modules/downloads';

    File::deleteDirectory($packagesRoot);
    config([
        'agovena.packages.optional_packages_path' => base_path('missing-optional-packages'),
        'agovena.packages.monorepo.repository' => 'https://github.com/milovd/optional-packages',
        'agovena.packages.monorepo.packages' => $packageMap,
    ]);
    app()->instance(MonorepoCheckout::class, $fake);
    app(ModuleManager::class)->refresh();
    app(ExtensionManager::class)->refresh();

    try {
        $exitCode = Artisan::call('agovena:seed-demo', [
            '--force' => true,
            '--skip-accounts' => true,
        ]);

        expect($exitCode)->toBe(1, Artisan::output())
            ->and(File::exists(storage_path('app/packages/modules/provisioning/module.json')))->toBeFalse()
            ->and(DB::table('agovena_modules')->where('module_id', 'provisioning')->exists())->toBeFalse();
    } finally {
        config([
            'agovena.packages.optional_packages_path' => $configuredPath,
            'agovena.packages.monorepo.repository' => $configuredRepository,
            'agovena.packages.monorepo.packages' => $configuredPackages,
        ]);
        app()->forgetInstance(MonorepoCheckout::class);
        File::deleteDirectory($packagesRoot);
        app(ModuleManager::class)->refresh();
        app(ExtensionManager::class)->refresh();
    }
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

it('does not clear existing data when account credentials cannot be delivered non-interactively', function (): void {
    $previous = getenv('AGOVENA_DEMO_PASSWORD');
    putenv('AGOVENA_DEMO_PASSWORD');

    try {
        Product::factory()->create(['slug' => 'existing-local-record']);

        $exitCode = Artisan::call('agovena:seed-demo', ['--force' => true, '--no-interaction' => true]);

        expect($exitCode)->toBe(1)
            ->and(Product::query()->where('slug', 'existing-local-record')->exists())->toBeTrue();
    } finally {
        putenv($previous === false ? 'AGOVENA_DEMO_PASSWORD' : 'AGOVENA_DEMO_PASSWORD='.$previous);
    }
});

it('refuses to generate credentials for captured Artisan output', function (): void {
    $previous = getenv('AGOVENA_DEMO_PASSWORD');
    putenv('AGOVENA_DEMO_PASSWORD');

    try {
        Product::factory()->create(['slug' => 'existing-local-record']);

        $exitCode = Artisan::call('agovena:seed-demo', ['--force' => true]);

        expect($exitCode)->toBe(1)
            ->and(Artisan::output())->not->toContain('Demo customer (')
            ->and(Product::query()->where('slug', 'existing-local-record')->exists())->toBeTrue();
    } finally {
        putenv($previous === false ? 'AGOVENA_DEMO_PASSWORD' : 'AGOVENA_DEMO_PASSWORD='.$previous);
    }
});

it('generates different high-entropy passwords for the demo customer and admin', function (): void {
    $passwords = DemoAccountPasswords::generate();

    expect($passwords->customer)->toMatch('/\A[A-Za-z0-9]{24}\z/')
        ->and($passwords->admin)->toMatch('/\A[A-Za-z0-9]{24}\z/')
        ->and($passwords->customer)->not->toBe($passwords->admin);
});

it('seeds hashed demo credentials without printing an explicitly supplied password', function (): void {
    $previous = getenv('AGOVENA_DEMO_PASSWORD');
    $password = str_repeat('x', 32);
    putenv('AGOVENA_DEMO_PASSWORD='.$password);

    try {
        $exitCode = Artisan::call('agovena:seed-demo', ['--force' => true, '--no-interaction' => true]);
        $output = Artisan::output();

        expect($exitCode)->toBe(0, $output)
            ->and($output)->not->toContain($password)
            ->and(User::query()->where('email', 'demo@agovena.com')->firstOrFail()->password)->not->toBe($password)
            ->and(User::query()->where('email', 'admin@agovena.com')->firstOrFail()->password)->not->toBe($password)
            ->and(Hash::check($password, User::query()->where('email', 'demo@agovena.com')->firstOrFail()->password))->toBeTrue()
            ->and(Hash::check($password, User::query()->where('email', 'admin@agovena.com')->firstOrFail()->password))->toBeTrue();
    } finally {
        putenv($previous === false ? 'AGOVENA_DEMO_PASSWORD' : 'AGOVENA_DEMO_PASSWORD='.$previous);
    }
});
