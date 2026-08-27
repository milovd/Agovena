<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Pterodactyl\PterodactylApi;
use Agovena\Extensions\Pterodactyl\PterodactylProviderException;

final class FakePterodactylApi implements PterodactylApi
{
    public function withConnection(array $settings): PterodactylApi
    {
        unset($settings);

        return $this;
    }

    /** @var array<string, array<string, mixed>> */
    public array $serversByExternalId = [];

    /** @var array<int, array<string, mixed>> */
    public array $serversById = [];

    /** @var list<string> */
    public array $powerCalls = [];

    public int $createCalls = 0;

    /** @var array<string, mixed> */
    public array $lastCreatePayload = [];

    public int $buildCalls = 0;

    public int $nextServerId = 10;

    public string $nextStatus = 'active';

    public bool $failCreate = false;

    public bool $failGet = false;

    public bool $failEgg = false;

    public bool $failQuota = false;

    public bool $failSuspend = false;

    public bool $failUnsuspend = false;

    public bool $failDelete = false;

    public bool $failBuild = false;

    public bool $failPower = false;

    public bool $unauthorized = false;

    public bool $unreachable = false;

    public bool $timeout = false;

    public bool $malformed = false;

    public function connectionTest(): array
    {
        $this->guardTransport();

        return ['object' => 'list', 'data' => []];
    }

    public function findServerByExternalId(string $externalId): ?array
    {
        $this->guardTransport();

        return $this->serversByExternalId[$externalId] ?? null;
    }

    public function getServer(int $serverId): array
    {
        $this->guardTransport();
        if ($this->failGet) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.provider_failed');
        }
        if ($this->malformed) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.malformed');
        }
        if (! isset($this->serversById[$serverId])) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.not_found', 404);
        }

        return $this->serversById[$serverId];
    }

    public function getEgg(int $nestId, int $eggId): array
    {
        $this->guardTransport();
        if ($this->failEgg) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.invalid_egg', 422);
        }

        return [
            'id' => $eggId,
            'docker_image' => 'ghcr.io/pterodactyl/games:java',
            'startup' => 'java -jar server.jar',
            'relationships' => [
                'variables' => [
                    'data' => [
                        ['attributes' => ['env_variable' => 'SERVER_JARFILE', 'default_value' => 'server.jar']],
                    ],
                ],
            ],
        ];
    }

    public function createServer(array $payload): array
    {
        $this->guardTransport();
        $this->createCalls++;
        $this->lastCreatePayload = $payload;
        if ($this->failCreate) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.create_failed');
        }
        if ($this->failQuota) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.quota', 400);
        }

        $id = $this->nextServerId++;
        $server = [
            'id' => $id,
            'external_id' => (string) ($payload['external_id'] ?? ''),
            'identifier' => 'abc'.$id,
            'uuid' => 'uuid-'.$id,
            'name' => (string) ($payload['name'] ?? 'server'),
            'status' => $this->nextStatus === 'active' ? null : $this->nextStatus,
            'suspended' => false,
            'allocation' => 1,
            'limits' => $payload['limits'] ?? [],
        ];
        $this->serversById[$id] = $server;
        if ($server['external_id'] !== '') {
            $this->serversByExternalId[$server['external_id']] = $server;
        }

        return $server;
    }

    public function suspend(int $serverId): void
    {
        $this->guardTransport();
        if ($this->failSuspend) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.provider_failed');
        }
        $this->serversById[$serverId]['suspended'] = true;
        $this->serversById[$serverId]['status'] = 'suspended';
    }

    public function unsuspend(int $serverId): void
    {
        $this->guardTransport();
        if ($this->failUnsuspend) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.provider_failed');
        }
        $this->serversById[$serverId]['suspended'] = false;
        $this->serversById[$serverId]['status'] = null;
    }

    public function delete(int $serverId): void
    {
        $this->guardTransport();
        if ($this->failDelete) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.provider_failed');
        }
        $external = $this->serversById[$serverId]['external_id'] ?? null;
        unset($this->serversById[$serverId]);
        if (is_string($external)) {
            unset($this->serversByExternalId[$external]);
        }
    }

    public function updateBuild(int $serverId, array $payload): array
    {
        $this->guardTransport();
        $this->buildCalls++;
        if ($this->failBuild) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.quota', 400);
        }
        $this->serversById[$serverId]['limits'] = [
            'memory' => $payload['memory'] ?? 0,
            'disk' => $payload['disk'] ?? 0,
            'cpu' => $payload['cpu'] ?? 0,
        ];

        return $this->serversById[$serverId];
    }

    public function clientServer(string $identifier): array
    {
        $this->guardTransport();

        return [
            'identifier' => $identifier,
            'current_state' => 'running',
        ];
    }

    public function power(string $identifier, string $signal): void
    {
        $this->guardTransport();
        if ($this->failPower) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.power_failed');
        }
        $this->powerCalls[] = $identifier.':'.$signal;
    }

    private function guardTransport(): void
    {
        if ($this->unauthorized) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.unauthorized', 401);
        }
        if ($this->timeout) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.timeout');
        }
        if ($this->unreachable) {
            throw PterodactylProviderException::failed('pterodactyl::messages.errors.unreachable');
        }
    }
}
