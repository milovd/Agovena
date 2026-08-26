<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

use App\Agovena\Extensions\Contracts\ClearsRuntimeRegistry;

final class RuntimeRegistry
{
    /** @var array<int, ClearsRuntimeRegistry> */
    private array $registries = [];

    public function register(ClearsRuntimeRegistry $registry): void
    {
        $this->registries[spl_object_id($registry)] = $registry;
    }

    public function clear(): void
    {
        foreach ($this->registries as $registry) {
            $registry->clear();
        }
    }
}
