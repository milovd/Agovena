<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\FailoverQueue;
use Illuminate\Queue\QueueManager;

final class AfterCommitFailoverQueue extends FailoverQueue
{
    public function __construct(
        QueueManager $manager,
        Dispatcher $events,
        array $connections,
    ) {
        parent::__construct($manager, $events, $connections);
        $this->dispatchAfterCommit = true;
    }
}
