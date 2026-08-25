<?php

declare(strict_types=1);

namespace App\Agovena\Audit;

use App\Models\AuditLog;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditLogExporter
{
    public function __construct(
        private readonly AuditLogQuery $query,
        private readonly AuditLogger $redactor,
    ) {}

    /** @param array<string, mixed> $filters */
    public function download(array $filters = []): StreamedResponse
    {
        $query = $this->query->build($filters)->reorder('id');
        $filename = 'agovena-audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'event_id', 'created_at', 'category', 'severity', 'outcome', 'action',
                'actor_type', 'actor_id', 'subject_type', 'subject_id', 'ip', 'user_agent',
                'request_id', 'correlation_id', 'route', 'method', 'status_code',
                'properties', 'before', 'after', 'context', 'integrity_hash',
            ]);

            $query->chunkById(500, function ($logs) use ($output): void {
                foreach ($logs as $log) {
                    /** @var AuditLog $log */
                    fputcsv($output, [
                        $this->csvCell($log->event_id),
                        $this->csvCell($log->created_at->toISOString()),
                        $this->csvCell($log->category),
                        $this->csvCell($log->severity),
                        $this->csvCell($log->outcome),
                        $this->csvCell($log->action),
                        $this->csvCell($log->actor_type),
                        $this->csvCell($log->actor_id),
                        $this->csvCell($log->subject_type),
                        $this->csvCell($log->subject_id),
                        $this->csvCell($log->ip),
                        $this->csvCell($log->user_agent),
                        $this->csvCell($log->request_id),
                        $this->csvCell($log->correlation_id),
                        $this->csvCell($log->route),
                        $this->csvCell($log->method),
                        $this->csvCell($log->status_code),
                        $this->jsonCell($log->properties),
                        $this->jsonCell($log->before),
                        $this->jsonCell($log->after),
                        $this->jsonCell($log->context),
                        $this->csvCell($log->integrity_hash),
                    ]);
                }
            });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function jsonCell(mixed $value): string
    {
        if (! is_array($value)) {
            return '';
        }

        return $this->csvCell((string) json_encode(
            $this->redactor->redactForOutput($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
