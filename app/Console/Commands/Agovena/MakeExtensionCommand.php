<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Scaffolding\ScaffoldingGenerator;
use Illuminate\Console\Command;
use Throwable;

final class MakeExtensionCommand extends Command
{
    protected $signature = 'agovena:make-extension {id} {--force}';

    protected $description = 'Create an Agovena extension scaffold';

    public function handle(ScaffoldingGenerator $generator): int
    {
        try {
            $path = $generator->extension((string) $this->argument('id'), (bool) $this->option('force'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Extension scaffold created at {$path}");

        return self::SUCCESS;
    }
}
