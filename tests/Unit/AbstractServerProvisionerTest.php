<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use Agovena\Modules\Provisioning\Support\AbstractServerProvisioner;
use Agovena\Modules\Provisioning\Support\ServerApi;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingDefinition;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Provisioning\ServiceInstanceInfo;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app(ModuleManager::class)->discover();
    app(ExtensionManager::class)->discover();
    installAndEnableModule('provisioning');
});

function makeGenericProvisionerTestApi(): ServerApi
{
    return new class implements ServerApi
    {
        public int $createCalls = 0;

        public string $status = 'active';

        /** @var array<string, mixed> */
        public array $lastPlanPayload = [];

        /** @var array<string, mixed> */
        public array $createResult = ['status' => 'active'];

        public function withConnection(array $settings): ServerApi
        {
            unset($settings);

            return $this;
        }

        public function connectionTest(): array
        {
            return ['ok' => true];
        }

        public function availableCapacity(array $requirements): int
        {
            unset($requirements);

            return 100;
        }

        public function findServerByExternalId(string $externalId): ?array
        {
            unset($externalId);

            return null;
        }

        public function getServer(string $externalId): array
        {
            return ['id' => $externalId, 'status' => $this->status];
        }

        public function createServer(array $payload): array
        {
            unset($payload);
            $this->createCalls++;

            return $this->createResult;
        }

        public function suspend(string $externalId): void
        {
            unset($externalId);
        }

        public function unsuspend(string $externalId): void
        {
            unset($externalId);
        }

        public function terminate(string $externalId): void
        {
            unset($externalId);
        }

        public function changePlan(string $externalId, array $payload): void
        {
            unset($externalId);
            $this->lastPlanPayload = $payload;
        }

        public function action(string $externalId, string $action): void
        {
            unset($externalId, $action);
        }
    };
}

function makeGenericProvisioner(ServerApi $api): AbstractServerProvisioner
{
    return new class(app(ExtensionSettingsRepository::class), $api) extends AbstractServerProvisioner
    {
        public function id(): string
        {
            return 'generic-test';
        }

        public function label(): string
        {
            return 'Generic test';
        }

        public function serverSettings(): array
        {
            return [
                new ExtensionSettingDefinition('api_url', 'API URL', required: true),
                new ExtensionSettingDefinition('api_token', 'API token', secret: true, required: true),
            ];
        }

        public function productSettings(): array
        {
            return [];
        }

        protected function buildCreatePayload(ServiceInstanceInfo $instance, array $providerSettings, string $externalId): array
        {
            return ['name' => $instance->label, 'settings' => $providerSettings, 'external_id' => $externalId];
        }
    };
}

function genericServiceInfo(ServiceInstance $instance): ServiceInstanceInfo
{
    return new ServiceInstanceInfo(
        id: $instance->id,
        label: $instance->number,
        status: $instance->status->value,
        providerKey: 'generic-test',
        externalRef: $instance->external_ref,
        meta: $instance->meta ?? [],
        serverSettings: is_array($instance->meta['server_settings'] ?? null) ? $instance->meta['server_settings'] : null,
        providerSettings: is_array($instance->meta['provider_settings'] ?? null) ? $instance->meta['provider_settings'] : null,
    );
}

it('does not treat a legacy external reference as a provider mapping', function (): void {
    $api = makeGenericProvisionerTestApi();
    $provisioner = makeGenericProvisioner($api);
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-GENERIC-LEGACY',
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'generic-test',
        'external_ref' => 'legacy-source-reference',
        'customer_email' => 'generic@example.test',
        'meta' => [
            'server_settings_required' => true,
            'server_settings' => ['api_url' => 'https://provider.example.test', 'api_token' => '[REDACTED]'],
        ],
    ]);

    expect(fn () => $provisioner->provision(genericServiceInfo($instance)))
        ->toThrow(ValidationException::class)
        ->and($api->createCalls)->toBe(0);
});

it('rejects a provider response without a provider resource id', function (): void {
    $api = makeGenericProvisionerTestApi();
    $provisioner = makeGenericProvisioner($api);
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-GENERIC-NO-ID',
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'generic-test',
        'customer_email' => 'generic@example.test',
        'meta' => [
            'server_settings_required' => true,
            'server_settings' => ['api_url' => 'https://provider.example.test', 'api_token' => '[REDACTED]'],
        ],
    ]);

    expect(fn () => $provisioner->provision(genericServiceInfo($instance)))
        ->toThrow(ValidationException::class);
});

it('keeps the generic scaffold explicitly unsupported', function (): void {
    $api = makeGenericProvisionerTestApi();
    $provisioner = makeGenericProvisioner($api);
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-GENERIC-UNKNOWN',
        'status' => ServiceInstanceStatus::Provisioning,
        'provider_key' => 'generic-test',
        'customer_email' => 'generic@example.test',
    ]);

    expect(fn () => $provisioner->provision(genericServiceInfo($instance)))
        ->toThrow(ValidationException::class)
        ->and($api->createCalls)->toBe(0);
});

it('does not attempt generic provider plan mutations', function (): void {
    $api = makeGenericProvisionerTestApi();
    $provisioner = makeGenericProvisioner($api);
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-GENERIC-PLAN',
        'status' => ServiceInstanceStatus::Active,
        'provider_key' => 'generic-test',
        'external_ref' => 'generic-42',
        'customer_email' => 'generic@example.test',
        'meta' => [
            'provider_mapping' => ['provider_id' => 'generic-42'],
            'server_settings_required' => true,
            'server_settings' => ['api_url' => 'https://provider.example.test', 'api_token' => '[REDACTED]'],
        ],
    ]);

    expect(fn () => $provisioner->changePlan(genericServiceInfo($instance), [
        'id' => 'target-plan',
        'provider_settings' => ['memory' => 4096],
        'server_settings' => ['node' => 'target-node'],
        'server_id' => 7,
        'target_settings' => ['feature' => false],
    ]))->toThrow(ValidationException::class)
        ->and($api->lastPlanPayload)->toBe([]);
});
