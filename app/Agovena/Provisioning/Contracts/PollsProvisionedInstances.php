<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

interface PollsProvisionedInstances
{
    public function pollProvisioning(): int;
}
