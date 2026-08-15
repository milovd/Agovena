<?php

declare(strict_types=1);

/**
 * Drain database-queue jobs and verify a dedicated proof job for native deploy smoke.
 */

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class NativeQueueProofJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put('agovena-native-queue-proof', 1, 3600);
    }
}

try {
    $emitError = static function (string $message): void {
        $oneLine = str_replace(["\r", "\n"], ' | ', $message);
        fwrite(STDOUT, "::error::native-queue-proof: {$oneLine}\n");
        fwrite(STDERR, "native-queue-proof: {$message}\n");
    };

    $dumpFailed = static function () use ($emitError): void {
        foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get() as $failed) {
            $excerpt = substr((string) $failed->exception, 0, 1500);
            $emitError("failed_job id={$failed->id}: {$excerpt}");
        }
    };

    $drain = static function () use ($emitError, $dumpFailed): void {
        for ($i = 0; $i < 50; $i++) {
            if ((int) DB::table('jobs')->count() === 0) {
                return;
            }

            $exit = Artisan::call('queue:work', [
                '--once' => true,
                '--tries' => 1,
                '--no-interaction' => true,
            ]);
            $output = trim(Artisan::output());
            if ($output !== '') {
                fwrite(STDOUT, $output."\n");
            }

            if ($exit !== 0) {
                $dumpFailed();
                $emitError("queue:work exited {$exit} with pending jobs");
                exit(1);
            }
        }

        $pending = (int) DB::table('jobs')->count();
        if ($pending !== 0) {
            $dumpFailed();
            $emitError("could not drain jobs (pending={$pending})");
            exit(1);
        }
    };

    $connection = (string) Config::get('queue.default');
    fwrite(STDOUT, "native-queue-proof: queue.default={$connection}\n");

    if ($connection !== 'database') {
        $emitError("expected queue.default=database, got {$connection}");
        exit(1);
    }

    $drain();

    Cache::put('agovena-native-queue-proof', 0, 3600);
    dispatch(new NativeQueueProofJob);

    if ((int) DB::table('jobs')->count() < 1) {
        $emitError('expected at least one job after dispatching NativeQueueProofJob');
        exit(1);
    }

    $drain();

    $proof = (int) (Cache::get('agovena-native-queue-proof') ?? 0);
    $failed = (int) DB::table('failed_jobs')->count();

    if ($proof !== 1) {
        $dumpFailed();
        $emitError("proof job did not run (proof={$proof})");
        exit(1);
    }

    if ($failed !== 0) {
        $dumpFailed();
        $emitError("failed_jobs={$failed}");
        exit(1);
    }

    fwrite(STDOUT, "native-queue-ok proof={$proof}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDOUT, '::error::native-queue-proof exception: '.str_replace(["\r", "\n"], ' | ', $e->getMessage())."\n");
    fwrite(STDERR, $e->getMessage()."\n".$e->getTraceAsString()."\n");
    exit(1);
}
