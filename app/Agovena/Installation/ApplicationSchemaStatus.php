<?php

declare(strict_types=1);

namespace App\Agovena\Installation;

use App\Agovena\Modules\ModuleManager;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\QueryException;
use Throwable;

final class ApplicationSchemaStatus
{
    /** @var list<string>|null */
    private ?array $pending = null;

    public function __construct(
        private readonly Migrator $migrator,
        private readonly ModuleManager $modules,
    ) {}

    public function refresh(): void
    {
        $this->pending = null;
    }

    public function isCurrent(): bool
    {
        return $this->pending() === [];
    }

    /**
     * @return list<string>
     */
    public function pending(): array
    {
        if ($this->pending !== null) {
            return $this->pending;
        }

        if (! $this->migrator->repositoryExists()) {
            return $this->pending = array_keys($this->migrationFiles());
        }

        $ran = $this->migrator->getRepository()->getRan();

        return $this->pending = array_values(array_diff(array_keys($this->migrationFiles()), $ran));
    }

    public function pendingCount(): int
    {
        return count($this->pending());
    }

    /**
     * @return array{
     *     pending: list<string>,
     *     pendingCount: int,
     *     upgradeCommand: string,
     *     migrateCommand: string
     * }
     */
    public function viewData(): array
    {
        $pending = $this->pending();

        return [
            'pending' => $pending,
            'pendingCount' => count($pending),
            'upgradeCommand' => 'php artisan agovena:upgrade',
            'migrateCommand' => 'php artisan migrate --force',
        ];
    }

    public function isMissingRelationException(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $message = $e->getMessage();

        return str_contains($message, 'no such table')
            || str_contains($message, 'Base table or view not found')
            || str_contains($message, 'Undefined table')
            || (str_contains($message, 'relation') && str_contains($message, 'does not exist'));
    }

    /**
     * @return array<string, string>
     */
    private function migrationFiles(): array
    {
        return $this->migrator->getMigrationFiles($this->migrationPaths());
    }

    /**
     * @return list<string>
     */
    private function migrationPaths(): array
    {
        $paths = array_merge($this->migrator->paths(), [database_path('migrations')]);

        try {
            foreach ($this->modules->discover() as $manifest) {
                if (! $this->modules->isEnabled($manifest->id)) {
                    continue;
                }

                $path = $manifest->path.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
                if (is_dir($path)) {
                    $paths[] = $path;
                }
            }
        } catch (Throwable) {
            // Module discovery is optional for Core schema detection.
        }

        return array_values(array_unique($paths));
    }
}
