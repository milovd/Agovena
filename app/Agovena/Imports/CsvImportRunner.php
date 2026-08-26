<?php

declare(strict_types=1);

namespace App\Agovena\Imports;

use App\Agovena\Imports\Contracts\ImportAdapter;
use InvalidArgumentException;
use SplFileObject;
use Throwable;

final class CsvImportRunner
{
    private const MAX_FILE_BYTES = 10_485_760;

    public function preview(string $path, ImportAdapter $adapter): ImportReport
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('The import file cannot be read.');
        }

        $size = filesize($path);
        if ($size === false || $size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('The import file exceeds the 10 MB limit.');
        }

        $file = new SplFileObject($path, 'rb');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $header = $file->fgetcsv(',', '"', '\\');
        if (! is_array($header) || $header === [null]) {
            throw new InvalidArgumentException('The import file has no CSV header.');
        }

        $headers = array_map(static fn (mixed $value): string => trim((string) $value), $header);
        if (count($headers) !== count(array_unique($headers)) || in_array('', $headers, true)) {
            throw new InvalidArgumentException('The CSV header must contain unique, non-empty names.');
        }

        $read = 0;
        $valid = 0;
        $duplicates = 0;
        $errors = 0;
        $candidates = [];
        $rowErrors = [];
        $seenExternalIds = [];
        $line = 1;

        while (! $file->eof()) {
            $values = $file->fgetcsv(',', '"', '\\');
            $line++;
            if (! is_array($values) || $values === [null] || (count($values) === 1 && trim((string) ($values[0] ?? '')) === '')) {
                continue;
            }

            $read++;
            if (count($values) !== count($headers)) {
                $errors++;
                $rowErrors[$line] = 'The CSV row does not match the header.';

                continue;
            }

            /** @var array<string, string|null> $row */
            $row = array_combine($headers, array_map(
                static fn (mixed $value): ?string => $value === null ? null : trim((string) $value),
                $values,
            ));

            try {
                $candidate = $adapter->map($row, $line);
                if ($candidate->externalId === '') {
                    throw new InvalidArgumentException('An external ID is required.');
                }
                if (isset($seenExternalIds[$candidate->externalId])) {
                    $duplicates++;

                    continue;
                }

                $seenExternalIds[$candidate->externalId] = true;
                $candidates[] = $candidate;
                $valid++;
            } catch (Throwable $exception) {
                $errors++;
                $rowErrors[$line] = $exception->getMessage();
            }
        }

        return new ImportReport(
            dryRun: true,
            read: $read,
            valid: $valid,
            duplicates: $duplicates,
            errors: $errors,
            candidates: $candidates,
            rowErrors: $rowErrors,
        );
    }
}
