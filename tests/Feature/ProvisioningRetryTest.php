<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\ProvisioningOrchestrator;
use Agovena\Modules\Provisioning\ProvisioningService;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\Contracts\ProvisionerLifecycle;
use App\Agovena\Provisioning\ProvisionerRegistry;

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
