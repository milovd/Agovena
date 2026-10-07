<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Agovena\Modules\ModuleManager;

function writeServiceIntegrityFixture(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-service-');
    file_put_contents($path, $contents);

    return $path;
}

/** @param list<string> $headers @param list<string|int> $row */
function writeServiceIntegrityCsv(array $headers, array $row): string
{
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, $headers);
    fputcsv($handle, $row);
    rewind($handle);
    $contents = stream_get_contents($handle);
    fclose($handle);

    return writeServiceIntegrityFixture($contents ?: '');
}

it('rejects service imports while provisioning is installed but disabled', function (): void {
    installAndEnableModules(['provisioning']);
    app(ModuleManager::class)->disable('provisioning');
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writeServiceIntegrityFixture("external_id,email,name\nC-SERVICE-DISABLED,disabled-service@example.test,Disabled Service Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $productPath = writeServiceIntegrityFixture("external_id,name,price_amount\nP-SERVICE-DISABLED,Disabled service product,3000\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $servicePath = writeServiceIntegrityFixture("external_id,customer_external_id,product_external_id,number,status,provider_key\nS-DISABLED,C-SERVICE-DISABLED,P-SERVICE-DISABLED,SVC-DISABLED,active,manual\n");

    $run = $executor->run($servicePath, $registry->for('csv', 'service_instance'), 'csv');

    expect($run->errors)->toBe(1)
        ->and(ServiceInstance::query()->where('number', 'SVC-DISABLED')->exists())->toBeFalse();

    unlink($customerPath);
    unlink($productPath);
    unlink($servicePath);
});

it('rejects a service mapped to another customers subscription', function (): void {
    installAndEnableModules(['subscriptions', 'provisioning']);
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writeServiceIntegrityFixture("external_id,email,name\nC-SERVICE-A,service-a@example.test,Service A\nC-SERVICE-B,service-b@example.test,Service B\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $productPath = writeServiceIntegrityFixture("external_id,name,price_amount\nP-SERVICE-A,Service product A,3000\nP-SERVICE-B,Service product B,4000\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $subscriptionPath = writeServiceIntegrityCsv(
        ['external_id', 'customer_external_id', 'product_external_id', 'number', 'status', 'interval', 'interval_count', 'price_amount', 'currency', 'quantity'],
        ['SUB-SERVICE-B', 'C-SERVICE-B', 'P-SERVICE-B', 'SUB-B', 'active', 'month', 1, 4000, 'EUR', 1],
    );
    $executor->run($subscriptionPath, $registry->for('csv', 'subscription'), 'csv');
    $servicePath = writeServiceIntegrityFixture("external_id,customer_external_id,product_external_id,subscription_external_id,number,status,provider_key\nS-MISMATCH,C-SERVICE-A,P-SERVICE-A,SUB-SERVICE-B,SVC-MISMATCH,active,manual\n");

    $run = $executor->run($servicePath, $registry->for('csv', 'service_instance'), 'csv');

    expect($run->errors)->toBe(1)
        ->and(ServiceInstance::query()->where('number', 'SVC-MISMATCH')->exists())->toBeFalse();

    unlink($customerPath);
    unlink($productPath);
    unlink($subscriptionPath);
    unlink($servicePath);
});
