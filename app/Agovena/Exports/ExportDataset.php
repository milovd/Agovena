<?php

declare(strict_types=1);

namespace App\Agovena\Exports;

/**
 * One exportable entity with a stable, ordered column set.
 *
 * Rows must contain exactly the keys returned by columns(), in that order, and
 * must never include credentials, secrets or raw provider payloads. Money is a
 * decimal string in the record currency; timestamps are ISO 8601 UTC.
 */
interface ExportDataset
{
    /** URL and file name key, also the XML root element. */
    public function key(): string;

    /** The view permission a staff member needs besides data.export. */
    public function permission(): string;

    /** XML element name of one record. */
    public function recordElement(): string;

    /** @return list<string> */
    public function columns(): array;

    public function count(ExportFilters $filters): int;

    /**
     * Rows streamed lazily so large stores never load everything in memory.
     *
     * @return iterable<array<string, string|int|bool|null>>
     */
    public function rows(ExportFilters $filters): iterable;
}
