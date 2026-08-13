<?php

declare(strict_types=1);

namespace App\Agovena\Operations;

use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Packages\PackageCatalog;
use App\Enums\PackageLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SystemOperationsStatus
{
    public function __construct(
        private readonly ApplicationSchemaStatus $schema,
        private readonly SchedulerHealth $scheduler,
        private readonly PackageCatalog $packages,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $schema = $this->schema->viewData();
        $updates = [];
        foreach ([...$this->packages->modules(), ...$this->packages->extensions()] as $row) {
            if ($row['lifecycle'] === PackageLifecycle::UpdateAvailable) {
                $updates[] = [
                    'name' => $row['name'],
                    'id' => $row['id'],
                    'version' => $row['version'],
                ];
            }
        }

        return [
            ...$schema,
            'platformVersion' => (string) config('agovena.version', '0.1.0'),
            'scheduler' => $this->scheduler->snapshot(),
            'queue' => (string) config('queue.default', ''),
            'mail' => (string) config('mail.default', ''),
            'failedJobs' => $this->failedJobCount(),
            'packageUpdates' => $updates,
        ];
    }

    private function failedJobCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }
}
