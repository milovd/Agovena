<?php

declare(strict_types=1);

namespace App\Agovena\Support;

use Illuminate\Support\Facades\DB;

/**
 * MySQL/MariaDB DDL implicitly commits the test transaction. Laravel's
 * nesting counter is then stale, so later DB::transaction() raises PDOException.
 * Reset the counter without opening a new transaction so RefreshDatabase can
 * still see the implicit commit and remigrate the next test.
 */
final class RecoversTestTransaction
{
    public static function afterDdl(): void
    {
        if (! app()->runningUnitTests()) {
            return;
        }

        $connection = DB::connection();
        $pdo = $connection->getPdo();
        if ($pdo->inTransaction() || $connection->transactionLevel() === 0) {
            return;
        }

        $connection->setPdo($pdo);
    }
}
