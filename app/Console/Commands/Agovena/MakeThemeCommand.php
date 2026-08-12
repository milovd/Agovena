<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Scaffolding\ScaffoldingGenerator;
use Illuminate\Console\Command;
use Throwable;

final class MakeThemeCommand extends Command
{
    protected $signature = 'agovena:make-theme {id} {--force}';

    protected $description = 'Create an Agovena theme scaffold';

    public function handle(ScaffoldingGenerator $generator): int
    {
        try {
            $path = $generator->theme((string) $this->argument('id'), (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Theme scaffold created at {$path}");

        return self::SUCCESS;
    }
}
