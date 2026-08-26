<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Audit;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Audit\AuditLogQuery;
use App\Models\AuditLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $category = '';

    public string $severity = '';

    public string $outcome = '';

    public string $actorType = '';

    public string $actorId = '';

    public string $action = '';

    public string $subjectType = '';

    public string $subjectId = '';

    public string $ip = '';

    public string $requestId = '';

    public string $correlationId = '';

    public string $method = '';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->authorize('audit.view');
    }

    public function updated(string $property): void
    {
        unset($property);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'category', 'severity', 'outcome', 'actorType', 'actorId', 'action',
            'subjectType', 'subjectId', 'ip', 'requestId', 'correlationId', 'method', 'from', 'to',
        ]);
        $this->resetPage();
    }

    /** @return array<string, string> */
    public function exportFilters(): array
    {
        return array_filter([
            'search' => $this->search,
            'category' => $this->category,
            'severity' => $this->severity,
            'outcome' => $this->outcome,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'action' => $this->action,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'ip' => $this->ip,
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
            'method' => $this->method,
            'from' => $this->from,
            'to' => $this->to,
        ], static fn (string $value): bool => $value !== '');
    }

    public function render(AdminRegistrar $admin, AuditLogQuery $auditQuery)
    {
        $logs = $auditQuery->build($this->exportFilters())->paginate(25);

        return view('livewire.admin.audit.index', [
            'logs' => $logs,
            'exportUrl' => route('admin.audit.export', $this->exportFilters()),
            'categories' => AuditLog::CATEGORIES,
            'severities' => AuditLog::SEVERITIES,
            'outcomes' => AuditLog::OUTCOMES,
        ])->layout('layouts.admin', [
            'title' => __('admin.audit.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
