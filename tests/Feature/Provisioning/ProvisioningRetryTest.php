<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ProvisioningService;
use Agovena\Modules\Provisioning\ServiceInstanceRuntimeSecretStore;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\Contracts\ProvisionerLifecycle;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

it('keeps transient provider failures retryable after recording failed state', function (): void {
    installAndEnableModule('provisioning');

    $registry = app(ProvisionerRegistry::class);
    $registry->clear();

    $provider = Mockery::mock(Provisioner::class, ProvisionerLifecycle::class);
    $provider->shouldReceive('id')->andReturn('retry-provider');
    $provider->shouldReceive('label')->andReturn('Retry provider');
    $provider->shouldReceive('provision')->once()->andThrow(new RuntimeException('temporary provider outage'));
    $registry->register($provider);

    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-RETRY-1',
        'status' => ServiceInstanceStatus::Pending,
        'customer_email' => 'retry@example.test',
        'provider_key' => 'retry-provider',
        'meta' => [],
    ]);

    expect(fn () => app(ProvisioningOrchestrator::class)->provision($instance))
        ->toThrow(RuntimeException::class, 'temporary provider outage');

    expect($instance->fresh()->status)->toBe(ServiceInstanceStatus::Failed);
});

it('compensation uses the explicit previous runtime settings after a failed plan mutation', function (): void {
    installAndEnableModule('provisioning');

    $registry = app(ProvisionerRegistry::class);
    $registry->clear();
    $from = Product::factory()->active()->create();
    $to = Product::factory()->active()->create();
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-PLAN-ROLLBACK-1',
        'status' => ServiceInstanceStatus::Active,
        'customer_email' => 'rollback@example.test',
        'product_id' => $from->id,
        'provider_key' => 'rollback-provider',
        'meta' => [],
    ]);
    $previousProviderSettings = ['location' => 'previous-location'];
    $previousServerSettings = ['node' => 'previous-node'];
    $targetProviderSettings = ['location' => 'target-location'];
    $targetServerSettings = ['node' => 'target-node'];
    app(ServiceInstanceRuntimeSecretStore::class)->put($instance->id, $targetServerSettings, $targetProviderSettings);
    $calls = [];
    $provider = Mockery::mock(Provisioner::class, ProvisionerLifecycle::class);
    $provider->shouldReceive('id')->andReturn('rollback-provider');
    $provider->shouldReceive('changePlan')->twice()->ordered()->andReturnUsing(function (ServiceInstanceInfo $info, string|array $plan) use (&$calls): void {
        $calls[] = [$info, $plan];
        if (count($calls) === 1) {
            throw new RuntimeException('provider mutation failed');
        }
    });
    $provider->shouldReceive('syncStatus')->once()->andReturn(new ServiceInstanceInfo(
        id: $instance->id,
        label: $instance->number,
        status: 'active',
        providerKey: 'rollback-provider',
        externalRef: null,
        meta: [],
        serverSettings: $previousServerSettings,
        providerSettings: $previousProviderSettings,
    ));
    $registry->register($provider);

    $exception = null;
    try {
        app(ProvisioningOrchestrator::class)->changePlan($instance, [
            'id' => (string) $to->id,
            'product_id' => $to->id,
            'provider_key' => 'rollback-provider',
            'provider_settings' => $targetProviderSettings,
            'server_settings' => $targetServerSettings,
            'previous_product_id' => $from->id,
            'previous_provider_key' => 'rollback-provider',
            'previous_provider_settings' => $previousProviderSettings,
            'previous_server_settings' => $previousServerSettings,
        ]);
    } catch (Throwable $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class);

    expect($calls[1][0]->providerSettings)->toBe($previousProviderSettings)
        ->and($calls[1][0]->serverSettings)->toBe($previousServerSettings)
        ->and($calls[1][1]['provider_settings'])->toBe($previousProviderSettings)
        ->and($calls[1][1]['server_settings'])->toBe($previousServerSettings);
});

it('requires manual review before a failed service can re-enter provisioning', function (): void {
    installAndEnableModule('provisioning');

    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-REVIEW-1',
        'status' => ServiceInstanceStatus::Failed,
        'customer_email' => 'review@example.test',
        'failure_message' => 'Provider did not confirm the resource.',
    ]);

    $service = app(ProvisioningService::class);
    $reviewed = $service->markManualReview($instance, 'Provider result requires operator confirmation.');

    expect($reviewed->status)->toBe(ServiceInstanceStatus::ManualReview)
        ->and($reviewed->failure_message)->toBe('Provider result requires operator confirmation.');

    $provisioning = $service->markProvisioning($reviewed);

    expect($provisioning->status)->toBe(ServiceInstanceStatus::Provisioning);
});
