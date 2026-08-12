<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Scaffolding\ScaffoldingGenerator;
use Illuminate\Console\Command;
use Throwable;

final class MakeModuleCommand extends Command
{
    protected $signature = 'agovena:make-module {id} {--force}';

    protected $description = 'Create an Agovena module scaffold';

    public function handle(ScaffoldingGenerator $generator): int
    {
        try {
            $path = $generator->module((string) $this->argument('id'), (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Module scaffold created at {$path}");

        return self::SUCCESS;
    }
}
