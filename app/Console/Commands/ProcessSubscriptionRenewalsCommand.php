<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Operations\CronStatisticsRecorder;
use App\Agovena\Subscriptions\ProcessesSubscriptionRenewals;
use Illuminate\Console\Command;

final class ProcessSubscriptionRenewalsCommand extends Command
{
    protected $signature = 'agovena:process-subscription-renewals';

    protected $description = 'Create renewal orders for subscriptions that are due for billing';

    public function handle(ModuleManager $modules): int
    {
        if (! $modules->isEnabled('subscriptions') || ! $this->laravel->bound(ProcessesSubscriptionRenewals::class)) {
            $this->comment('Subscriptions module is not enabled.');

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
