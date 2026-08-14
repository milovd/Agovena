<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agovena\Modules\ModuleManager;
use App\Agovena\Provisioning\Contracts\PollsProvisionedInstances;
use Illuminate\Console\Command;

final class SyncProvisioningInstancesCommand extends Command
{
    protected $signature = 'agovena:sync-provisioning';

    protected $description = 'Poll in-progress provisioned services through their provider';

    public function handle(ModuleManager $modules): int
    {
        if (! $modules->isEnabled('provisioning') || ! $this->laravel->bound(PollsProvisionedInstances::class)) {
            $this->comment('Provisioning module is not enabled.');

            return self::SUCCESS;
        }

        $synced = app(PollsProvisionedInstances::class)->pollProvisioning();
        $this->info("Synced {$synced} provisioning instance(s).");

        return self::SUCCESS;
    }
}
