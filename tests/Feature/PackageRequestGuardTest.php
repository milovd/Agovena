<?php

declare(strict_types=1);

use Agovena\Modules\DigitalDelivery\Http\Livewire\Admin\CustomerSecrets;
use App\Agovena\Abuse\SecurityAbuseService;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Modules\ModuleManager;
use App\Models\Customer;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/*
 * Request guards that live in first-party packages. Released packages before the
 * listed version predate these guards and are skipped; operators get them by updating.
 */

function skipWhenPackageOlderThan(ExtensionManager|ModuleManager $manager, string $id, string $fixedIn): void
{
    $version = $manager->manifest($id)?->version;
    if ($version === null || version_compare($version, $fixedIn, '<')) {
        test()->markTestSkipped("{$id} ".($version ?? 'missing')." predates this guard ({$fixedIn}).");
    }
}

test('suspended users cannot open a provider checkout page', function (string $extension, string $path) {
    skipWhenPackageOlderThan(app(ExtensionManager::class), $extension, '1.0.1');
    installAndEnableExtension($extension);
    $customer = Customer::factory()->create();
    app(SecurityAbuseService::class)->suspendUser($customer->user, 'test');

    $this->actingAs($customer->user)->get($path)->assertForbidden();
})->with([
    'paypal' => ['paypal', '/paypal/checkout'],
    'paddle' => ['paddle', '/paddle/checkout'],
]);

test('an open customer secrets section stops rendering once its view permission is revoked', function () {
    skipWhenPackageOlderThan(app(ModuleManager::class), 'digital-delivery', '1.1.0');
    installAndEnableModules(['digital-delivery']);
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->createStaff(permissions: ['digital_delivery.view', 'customers.view']))
        ->test(CustomerSecrets::class, ['customer' => $customer])
        ->assertOk();

    Livewire::actingAs($this->createStaff(['email' => 'no-secrets@example.test'], permissions: ['customers.view']))
        ->test(CustomerSecrets::class, ['customer' => $customer])
        ->assertForbidden();
});
