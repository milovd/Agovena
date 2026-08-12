<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning;

use App\Agovena\Provisioning\Contracts\Provisioner;

final class ProvisionerRegistry
{
    /** @var array<string, Provisioner> */
    private array $items = [];

    public function register(Provisioner $provisioner): void
    {
        $this->items[$provisioner->id()] = $provisioner;
    }

    public function get(string $id): ?Provisioner
    {
        return $this->items[$id] ?? null;
    }

    /** @return list<Provisioner> */
    public function all(): array
    {
        return array_values($this->items);
    }

    public function clear(): void
    {
        $this->items = [];
    }
}
