<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Permissions\SyncRegisteredPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('agovena:sync-permissions')]
#[Description('Sync registered Admin permissions onto the owner role')]
final class SyncPermissionsCommand extends Command
{
    public function handle(SyncRegisteredPermissions $sync): int
    {
        $sync(force: true);
        $this->info(__('admin.permissions_synced'));

        return self::SUCCESS;
    }
}
