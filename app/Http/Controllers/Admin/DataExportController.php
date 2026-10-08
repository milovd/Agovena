<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Agovena\Exports\DataExporter;
use App\Agovena\Exports\ExportDatasetRegistry;
use App\Agovena\Exports\ExportFilters;
use App\Agovena\Exports\ExportFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DataExportController
{
    public function __invoke(Request $request, ExportDatasetRegistry $registry, DataExporter $exporter): StreamedResponse
    {
        Gate::authorize(ExportDatasetRegistry::PERMISSION);

        $toRules = ['nullable', 'date_format:Y-m-d'];
        if (is_string($request->query('created_from')) && $request->query('created_from') !== '') {
            $toRules[] = 'after_or_equal:created_from';
        }

        /** @var array{entity: string, format: string, created_from?: string|null, created_to?: string|null} $validated */
        $validated = $request->validate([
            'entity' => ['required', 'string', Rule::in(array_keys($registry->all()))],
            'format' => ['required', 'string', Rule::enum(ExportFormat::class)],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => $toRules,
        ]);

        $dataset = $registry->find($validated['entity']);
        abort_if($dataset === null, 404);
        Gate::authorize($dataset->permission());

        return $exporter->download(
            $dataset,
            ExportFormat::from($validated['format']),
            ExportFilters::fromDates($validated['created_from'] ?? null, $validated['created_to'] ?? null),
        );
    }
}
