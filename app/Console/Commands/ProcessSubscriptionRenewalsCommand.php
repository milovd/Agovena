<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Operations\CronStatisticsRecorder;
use App\Agovena\Subscriptions\ProcessesSubscriptionRenewals;
use Illuminate\Console\Command;

final class ProcessSubscriptionRenewalsCommand extends Command
{
    protected $signature = 'agovena:process-subscription-renewals';

    protected $description = 'Create renewal orders for subscriptions that are due for billing';

    public function handle(): int
    {
        if (! $this->laravel->bound(ProcessesSubscriptionRenewals::class)) {
            $this->comment('Recurring capability is not available.');

            return self::SUCCESS;
        }

        $processed = app(ProcessesSubscriptionRenewals::class)->processDue();
        app(CronStatisticsRecorder::class)->recordRun('subscription-renewals', [
            'subscription_renewals' => $processed,
        ]);
        $this->info("Processed {$processed} subscription(s).");

        return self::SUCCESS;
    }
}
