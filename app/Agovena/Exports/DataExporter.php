<?php

declare(strict_types=1);

namespace App\Agovena\Exports;

use App\Agovena\Audit\AuditLogger;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one dataset as a download and records the export in the audit log.
 * Callers authorize first; the audit entry holds only metadata, never rows.
 */
final class DataExporter
{
    public function __construct(
        private readonly ExportStreamWriter $writer,
        private readonly AuditLogger $audit,
    ) {}

    public function download(ExportDataset $dataset, ExportFormat $format, ExportFilters $filters): StreamedResponse
    {
        $this->audit->log('data.exported', null, [
            'entity' => $dataset->key(),
            'format' => $format->value,
            'filters' => $filters->toArray(),
            'row_count' => $dataset->count($filters),
        ], category: 'admin');

        $filename = sprintf('agovena-%s-%s.%s', $dataset->key(), now()->utc()->format('Y-m-d'), $format->extension());

        return response()->streamDownload(function () use ($dataset, $format, $filters): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            $this->writer->write($output, $format, $dataset, $dataset->rows($filters));
            fclose($output);
        }, $filename, [
            'Content-Type' => $format->contentType(),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
