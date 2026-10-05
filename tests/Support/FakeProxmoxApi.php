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

    public int $startCalls = 0;

    public bool $failCreate = false;

    public bool $failClone = false;

    public bool $failStart = false;

    /** Simulates a clone task that created the VM but whose task polling timed out. */
    public bool $timeoutAfterClone = false;

    public bool $unauthorized = false;

    public bool $unreachable = false;

    public bool $timeout = false;

    /** @var list<array<string, mixed>> */
    public array $clonePayloads = [];

    /** @var list<array{vmid: int, payload: array<string, mixed>}> */
    public array $configUpdates = [];

    /** @var list<array{node: string, vmid: int, disk: string, size: string}> */
    public array $resizeCalls = [];

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
        $this->clonePayloads[] = $payload;
        if ($this->failCreate || $this->failClone) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.create_failed');
        }

        $vmid = (int) ($payload['newid'] ?? $this->nextVmId++);
        $config = [
            'boot' => 'order=scsi0;net0',
            'scsi0' => 'local-lvm:vm-'.$vmid.'-disk-0,size=20G',
            'net0' => 'virtio=AA:BB:CC:DD:EE:FF,bridge=vmbr0',
        ];
        if (is_string($payload['description'] ?? null)) {
            $config['description'] = $payload['description'];
        }
        $this->vms[$vmid] = [
            'node' => $node,
            'template' => $templateVmid,
            'name' => (string) ($payload['name'] ?? 'vm-'.$vmid),
            'config' => $config,
        ];
        $this->statusByKey[$node.':'.$vmid] = ['status' => 'stopped'];

        if ($this->timeoutAfterClone) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.timeout');
        }

        return 'UPID:pve:'.$vmid.':00000000:00000000:00000000:00000000:qmclone:200:root@pam:';
    }

    public function updateConfig(string $node, int $vmid, array $payload): void
    {
        $this->guardTransport();
        if (! isset($this->vms[$vmid])) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.not_found', 404);
        }
        $this->configUpdates[] = ['vmid' => $vmid, 'payload' => $payload];
        $this->vms[$vmid]['config'] = array_merge($this->vms[$vmid]['config'], $payload);
    }

    public function resizeDisk(string $node, int $vmid, string $disk, string $size): void
    {
        $this->guardTransport();
        $this->resizeCalls[] = ['node' => $node, 'vmid' => $vmid, 'disk' => $disk, 'size' => $size];
        $current = $this->vms[$vmid]['config'][$disk] ?? null;
        if (! is_string($current) || preg_match('/size=(\d+)G/', $current, $matches) !== 1
            || preg_match('/\A(\d+)G\z/', $size, $requested) !== 1
        ) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.provider_failed');
        }
        // Proxmox VE: "shrinking disks is not supported".
        if ((int) $requested[1] < (int) $matches[1]) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.provider_failed');
        }
        $this->vms[$vmid]['config'][$disk] = (string) preg_replace('/size=\d+G/', 'size='.$requested[1].'G', $current);
    }

    public function start(string $node, int $vmid): void
    {
        $this->guardTransport();
        $this->startCalls++;
        if ($this->failStart) {
            throw ProxmoxProviderException::failed('proxmox::messages.errors.provider_failed');
        }
        // Proxmox VE vm_start dies with "VM <vmid> already running".
        if (($this->statusByKey[$node.':'.$vmid]['status'] ?? null) === 'running') {
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
