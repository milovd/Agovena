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
use Illuminate\Support\Facades\Queue;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class NativeQueueProofJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::increment('agovena-native-queue-proof');
    }
}

$fail = static function (string $message) : never {
    echo "::error::native-queue-proof: {$message}\n";
    exit(1);
};

$dumpFailed = static function () : void {
    foreach (DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get() as $failed) {
        $excerpt = substr((string) $failed->exception, 0, 1200);
        echo "::error::failed_job id={$failed->id}::".str_replace("\n", ' | ', $excerpt)."\n";
        fwrite(STDERR, (string) $failed->exception."\n----\n");
    }
};

$drain = static function () use ($fail, $dumpFailed) : void {
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
            $fail("queue:work exited {$exit} with pending jobs");
        }
    }

    $pending = (int) DB::table('jobs')->count();
    if ($pending !== 0) {
        $dumpFailed();
        $fail("could not drain jobs (pending={$pending})");
    }
};

$connection = (string) Config::get('queue.default');
fwrite(STDOUT, "native-queue-proof: queue.default={$connection} driver=".Queue::getDefaultDriver()."\n");

if ($connection !== 'database') {
    $fail("expected queue.default=database, got {$connection}");
}

// Drain commerce/notification jobs from the preceding order smoke first.
$drain();

Cache::put('agovena-native-queue-proof', 0);
dispatch(new NativeQueueProofJob());

if ((int) DB::table('jobs')->count() < 1) {
    $fail('expected at least one job after dispatching NativeQueueProofJob');
}

$drain();

$proof = (int) (Cache::get('agovena-native-queue-proof') ?? 0);
$failed = (int) DB::table('failed_jobs')->count();

if ($proof !== 1) {
    $dumpFailed();
    $fail("proof job did not run (proof={$proof})");
}

if ($failed !== 0) {
    $dumpFailed();
    $fail("failed_jobs={$failed}");
}

fwrite(STDOUT, "native-queue-ok proof={$proof}\n");
