<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Agovena\Modules\Subscriptions\SubscriptionService;
use App\Agovena\Modules\ModuleManager;
use Illuminate\Console\Command;

final class ProcessSubscriptionRenewalsCommand extends Command
{
    protected $signature = 'agovena:process-subscription-renewals';

    protected $description = 'Create renewal orders for subscriptions that are due for billing';

    public function handle(ModuleManager $modules): int
    {
        if (! $modules->isEnabled('subscriptions')) {
            $this->comment('Subscriptions module is not enabled.');

            return self::SUCCESS;
        }

        $processed = app(SubscriptionService::class)->processDue();
        $this->info("Processed {$processed} subscription(s).");

        return self::SUCCESS;
    }
}
