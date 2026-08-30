<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Queue\Connectors\FailoverConnector;

final class AfterCommitFailoverConnector extends FailoverConnector
{
    public function connect(array $config): AfterCommitFailoverQueue
    {
        return new AfterCommitFailoverQueue(
            $this->manager,
            $this->events,
            $config['connections'],
        );
    }
}
