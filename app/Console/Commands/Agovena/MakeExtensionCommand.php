<?php

declare(strict_types=1);

namespace App\Console\Commands\Agovena;

use App\Agovena\Scaffolding\ScaffoldingGenerator;
use Illuminate\Console\Command;
use Throwable;

final class MakeExtensionCommand extends Command
{
    protected $signature = 'agovena:make-extension {id} {--category=other : payment_gateway, provisioning, shipping, authentication, storage, notifications, analytics, tax, or other} {--force}';

    protected $description = 'Create an Agovena extension scaffold';

    public function handle(ScaffoldingGenerator $generator): int
    {
        try {
            $path = $generator->extension(
                (string) $this->argument('id'),
                (bool) $this->option('force'),
                (string) $this->option('category'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Extension scaffold created at {$path}");

        return self::SUCCESS;
    }
}
