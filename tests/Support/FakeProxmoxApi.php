<?php

declare(strict_types=1);

namespace Tests\Support;

use Agovena\Extensions\Proxmox\ProxmoxApi;
use Agovena\Extensions\Proxmox\ProxmoxProviderException;

final class FakeProxmoxApi implements ProxmoxApi
{
    /** @var array<int, array<string, mixed>> */
    public array $vms = [];

    /** @var array<string, array<string, mixed>> */
    public array $statusByKey = [];

    public int $nextVmId = 200;

    public int $cloneCalls = 0;

    public int $deleteCalls = 0;

    public bool $failCreate = false;

    public bool $failClone = false;

    public bool $failStart = false;

    public bool $unauthorized = false;

    public bool $unreachable = false;

    public bool $timeout = false;

    /** @var array{memory_free: int|float, cpu_cores: int|float, storage_free: int|float} */
    public array $nodeCapacity = [
        'memory_free' => 1024 * 1024 * 1024 * 1024,
        'cpu_cores' => 100,
        'storage_free' => 1024 * 1024 * 1024 * 1024,
    ];

    public function withConnection(array $settings): ProxmoxApi
    {
        unset($settings);

        return $this;
    }

    public function connectionTest(): array
    {
        $this->guardTransport();

        return ['data' => ['version' => '8.2.0']];
    }

    public function nodeCapacity(string $node, string $storage): array
    {
        unset($node, $storage);
        $this->guardTransport();

        return $this->nodeCapacity;
    }

    public function nextVmId(): int
    {
        $this->guardTransport();

        return $this->nextVmId;
    }

    public function cloneVm(string $node, int $templateVmid, array $payload): string
    {
        $this->guardTransport();
        $this->cloneCalls++;
        if ($this->failCreate || $this->failClone) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.create_failed');
        }

        $vmid = (int) ($payload['newid'] ?? $this->nextVmId++);
        $this->vms[$vmid] = [
            'node' => $node,
            'template' => $templateVmid,
            'name' => (string) ($payload['name'] ?? 'vm-'.$vmid),
            'config' => [
                'scsi0' => 'local-lvm:vm-'.$vmid.'-disk-0,size=20G',
                'net0' => 'virtio=AA:BB:CC:DD:EE:FF,bridge=vmbr0',
            ],
        ];
        $this->statusByKey[$node.':'.$vmid] = ['status' => 'stopped'];

        return 'UPID:pve:'.$vmid.':00000000:00000000:00000000:00000000:qmclone:200:root@pam:';
    }

    public function updateConfig(string $node, int $vmid, array $payload): void
    {
        $this->guardTransport();
        if (! isset($this->vms[$vmid])) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.not_found', 404);
        }
        $this->vms[$vmid]['config'] = array_merge($this->vms[$vmid]['config'], $payload);
    }

    public function start(string $node, int $vmid): void
    {
        $this->guardTransport();
        if ($this->failStart) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.provider_failed');
        }
        $this->statusByKey[$node.':'.$vmid] = ['status' => 'running'];
    }

    public function stop(string $node, int $vmid): void
    {
        $this->guardTransport();
        $this->statusByKey[$node.':'.$vmid] = ['status' => 'stopped'];
    }

    public function deleteVm(string $node, int $vmid): void
    {
        $this->guardTransport();
        $this->deleteCalls++;
        unset($this->vms[$vmid], $this->statusByKey[$node.':'.$vmid]);
    }

    public function currentStatus(string $node, int $vmid): array
    {
        $this->guardTransport();

        return $this->statusByKey[$node.':'.$vmid] ?? ['status' => 'unknown'];
    }

    public function findVmByName(string $node, string $name): ?array
    {
        $this->guardTransport();
        foreach ($this->vms as $vmid => $vm) {
            if ((string) ($vm['node'] ?? '') === $node && (string) ($vm['name'] ?? '') === $name) {
                return ['node' => $node, 'vmid' => (int) $vmid, 'name' => $name];
            }
        }

        return null;
    }

    public function findVmConfig(string $node, int $vmid): ?array
    {
        $this->guardTransport();
        if (! isset($this->vms[$vmid])) {
            return null;
        }

        return array_merge(['name' => $this->vms[$vmid]['name'] ?? 'vm'], $this->vms[$vmid]['config'] ?? []);
    }

    private function guardTransport(): void
    {
        if ($this->unauthorized) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.unauthorized', 401);
        }
        if ($this->unreachable) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.unreachable');
        }
        if ($this->timeout) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.timeout');
        }
    }
}
