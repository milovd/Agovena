<?php

use App\Queue\AfterCommitQueueOutbox;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('agovena:recover-queue-outbox {--limit=100}', function (AfterCommitQueueOutbox $outbox): int {
    $recovered = $outbox->recover((int) $this->option('limit'));
    $this->info("Recovered queue outbox entries={$recovered}");

    return 0;
})->purpose('Replay durable after-commit queue entries');
