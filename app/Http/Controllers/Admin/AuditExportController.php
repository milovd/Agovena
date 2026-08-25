<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Agovena\Audit\AuditLogExporter;
use App\Agovena\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditExportController
{
    public function __invoke(Request $request, AuditLogExporter $exporter, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('audit.view');
        $filters = $this->filters($request);
        $audit->log('audit.exported', null, ['filters' => $filters]);

        return $exporter->download($filters);
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        $allowed = [
            'search', 'category', 'severity', 'outcome', 'actor_type', 'actor_id',
            'action', 'subject_type', 'subject_id', 'ip', 'request_id',
            'correlation_id', 'from', 'to', 'method',
        ];
        $filters = [];

        foreach ($allowed as $key) {
            $value = $request->query($key);
            if (is_string($value) && trim($value) !== '') {
                $filters[$key] = mb_substr(trim($value), 0, 255);
            }
        }

        return $filters;
    }
}
