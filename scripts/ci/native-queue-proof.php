<?php

declare(strict_types=1);

/**
 * Drain database-queue jobs and verify a dedicated proof job for native deploy smoke.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$drain = static function () use (&$drain): void {
    for ($i = 0; $i < 50; $i++) {
        if ((int) DB::table('jobs')->count() === 0) {
            return;
        }

        $exit = Artisan::call('queue:work', [
            '--once' => true,
            '--tries' => 1,
            '--no-interaction' => true,
        ]);

        if ($exit !== 0) {
            fwrite(STDERR, "native-queue-proof: queue:work exited {$exit}\n");
            fwrite(STDERR, Artisan::output());
            foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(3)->get() as $failed) {
                fwrite(STDERR, (string) $failed->exception."\n----\n");
            }
            exit(1);
        }
    }

    $pending = (int) DB::table('jobs')->count();
    if ($pending !== 0) {
        fwrite(STDERR, "native-queue-proof: could not drain jobs (pending={$pending})\n");
        exit(1);
    }
};

// Drain commerce/notification jobs from the preceding order smoke first.
$drain();

Cache::put('agovena-native-queue-proof', 0);

dispatch(function (): void {
    Cache::increment('agovena-native-queue-proof');
});

if ((int) DB::table('jobs')->count() < 1) {
    fwrite(STDERR, "native-queue-proof: expected at least one job after dispatch\n");
    exit(1);
}

$drain();

$proof = (int) (Cache::get('agovena-native-queue-proof') ?? 0);
$failed = (int) DB::table('failed_jobs')->count();

if ($proof !== 1) {
    fwrite(STDERR, "native-queue-proof: proof job did not run (proof={$proof})\n");
    exit(1);
}

if ($failed !== 0) {
    fwrite(STDERR, "native-queue-proof: failed_jobs={$failed}\n");
    foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(3)->get() as $row) {
        fwrite(STDERR, (string) $row->exception."\n----\n");
    }
    exit(1);
}

fwrite(STDOUT, "native-queue-ok proof={$proof}\n");
