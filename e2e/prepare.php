<?php

declare(strict_types=1);

use Agovena\Modules\Digital\Models\DigitalAsset;
use Agovena\Modules\Events\Enums\EventStatus;
use Agovena\Modules\Events\Models\Event;
use Agovena\Modules\Events\Models\EventPerformance;
use Agovena\Modules\Events\Models\EventTicketType;
use Agovena\Modules\Shipping\Enums\ShippingMethodType;
use Agovena\Modules\Shipping\Models\ShippingMethod;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Customer\CustomerRegistrationMode;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\ProductOptionType;
use App\Enums\ProductStatus;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$modules = $app->make(ModuleManager::class);
foreach (['inventory', 'shipping', 'digital', 'subscriptions', 'provisioning', 'events'] as $id) {
    if (! $modules->isInstalled($id)) {
        $modules->install($id);
    }
    $modules->enable($id);
}

$extensions = $app->make(ExtensionManager::class);
if (! $extensions->isInstalled('manual-payment')) {
    $extensions->install('manual-payment');
}
$extensions->enable('manual-payment');

$app->make(SyncRegisteredPermissions::class)(force: true);

$app->make(SettingsRepository::class)->set(
    'store',
    'customer_registration',
    CustomerRegistrationMode::Optional->value,
);

DiscountCode::query()->updateOrCreate(
    ['code' => 'E2ESAVE'],
    [
        'type' => 'percent',
        'value' => 10,
        'is_active' => true,
        'min_subtotal_amount' => 0,
    ],
);

$capabilities = $app->make(ProductCapabilityManager::class);

if (! ShippingMethod::query()->where('code', 'e2e-standard')->exists()) {
    ShippingMethod::query()->create([
        'name' => 'Standard delivery',
        'code' => 'e2e-standard',
        'type' => ShippingMethodType::Flat,
        'config' => ['amount' => 495],
        'currency' => 'EUR',
        'is_active' => true,
        'sort' => 1,
    ]);
}

function e2eProduct(string $slug, string $name, int $price): Product
{
    $product = Product::query()->where('slug', $slug)->first();
    if ($product === null) {
        $product = Product::query()->create([
            'name' => $name,
            'slug' => $slug,
            'sku' => strtoupper($slug),
            'description' => $name,
            'status' => ProductStatus::Active,
            'price_amount' => $price,
            'currency' => 'EUR',
        ]);
    }

    $product->forceFill(['created_at' => now()->subYears(2), 'updated_at' => now()->subYears(2)])->save();

    return $product;
}

$digital = e2eProduct('e2e-digital', 'E2E Field guide', 1200);
$capabilities->enable($digital, 'digital');
$path = 'digital/'.$digital->id.'/guide.txt';
Storage::disk('local')->put($path, 'guide');
DigitalAsset::query()->updateOrCreate(
    ['product_id' => $digital->id, 'filename' => 'guide.txt'],
    [
        'label' => 'Guide',
        'disk' => 'local',
        'path' => $path,
        'download_limit' => 3,
        'is_active' => true,
    ],
);

$physical = e2eProduct('e2e-physical', 'E2E Desk lamp', 2500);
$capabilities->enable($physical, 'physical');
$capabilities->enable($physical, 'shippable', ['weight_grams' => 800]);

$vps = e2eProduct('e2e-vps', 'E2E Nova VPS', 4000);
$capabilities->enable($vps, 'subscribable', [
    'interval' => 'month',
    'interval_count' => 1,
    'trial_days' => 0,
]);
$capabilities->enable($vps, 'provisionable', ['provider_key' => 'manual']);
$os = ProductOption::query()->firstOrCreate(
    ['product_id' => $vps->id, 'key' => 'os'],
    [
        'label' => 'Operating system',
        'type' => ProductOptionType::Select,
        'is_required' => true,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ],
);
ProductOptionChoice::query()->firstOrCreate(
    ['product_option_id' => $os->id, 'value' => 'ubuntu'],
    [
        'label' => 'Ubuntu',
        'price_adjustment_amount' => 0,
        'sort' => 1,
        'is_active' => true,
    ],
);

$ticket = e2eProduct('e2e-ticket', 'E2E Stalls ticket', 3500);
$capabilities->enable($ticket, 'event_ticket');
$event = Event::query()->firstOrCreate(
    ['slug' => 'e2e-spring-concert'],
    [
        'name' => 'E2E Spring Concert',
        'venue' => 'Stadsschouwburg',
        'status' => EventStatus::Published,
    ],
);
$performance = EventPerformance::query()->firstOrCreate(
    ['event_id' => $event->id],
    [
        'starts_at' => now()->addWeek(),
        'capacity' => 40,
        'venue' => $event->venue,
    ],
);
EventTicketType::query()->firstOrCreate(
    ['product_id' => $ticket->id],
    [
        'event_id' => $event->id,
        'performance_id' => $performance->id,
        'name' => 'Stalls',
    ],
);

fwrite(STDOUT, "E2E catalog ready (digital, physical, vps, ticket).\n");
