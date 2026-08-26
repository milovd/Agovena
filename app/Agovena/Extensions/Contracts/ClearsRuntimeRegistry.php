<?php

declare(strict_types=1);

namespace App\Agovena\Extensions\Contracts;

interface ClearsRuntimeRegistry
{
    public function clear(): void;
}
