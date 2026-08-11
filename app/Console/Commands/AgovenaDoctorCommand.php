<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class AgovenaDoctorCommand extends Command
{
    protected $signature = 'agovena:doctor';

    protected $description = 'Check Agovena runtime requirements and common misconfigurations';

    public function handle(): int
    {
        $this->info('Agovena doctor');
        $this->newLine();

        $checks = [
            'PHP version (>= 8.3)' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'OpenSSL extension' => extension_loaded('openssl'),
            'PDO extension' => extension_loaded('pdo'),
            'Mbstring extension' => extension_loaded('mbstring'),
            'Tokenizer extension' => extension_loaded('tokenizer'),
            'XML extension' => extension_loaded('xml'),
            'Ctype extension' => extension_loaded('ctype'),
            'JSON extension' => extension_loaded('json'),
            'Fileinfo extension' => extension_loaded('fileinfo'),
            'APP_KEY set' => filled(config('app.key')),
            'Storage path writable' => is_writable(storage_path()),
            'Bootstrap cache writable' => is_writable(base_path('bootstrap/cache')),
        ];

        $failed = 0;

        foreach ($checks as $label => $ok) {
            if ($ok) {
                $this->line("<info>PASS</info>  {$label}");
            } else {
                $this->line("<error>FAIL</error>  {$label}");
                $failed++;
            }
        }

        $this->newLine();

        if ($failed > 0) {
            $this->error("{$failed} check(s) failed.");

            return self::FAILURE;
        }

        $this->info('All checks passed.');

        return self::SUCCESS;
    }
}
