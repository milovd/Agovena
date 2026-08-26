<?php

declare(strict_types=1);

namespace App\Agovena\Backups;

final class BackupArtifactVerifier
{
    public function verify(string $root): BackupVerificationResult
    {
        $root = realpath($root);
        if ($root === false || ! is_dir($root)) {
            return new BackupVerificationResult(false, [], ['root']);
        }

        $files = ['.env', 'database/database.sqlite'];
        $directories = ['storage/app/private', 'storage/app/public'];
        $checked = [...$files, ...$directories];
        $missing = [];

        foreach ($files as $relative) {
            if (! is_file($root.DIRECTORY_SEPARATOR.$relative)) {
                $missing[] = $relative;
            }
        }
        foreach ($directories as $relative) {
            if (! is_dir($root.DIRECTORY_SEPARATOR.$relative)) {
                $missing[] = $relative;
            }
        }

        return new BackupVerificationResult($missing === [], $checked, $missing);
    }
}
