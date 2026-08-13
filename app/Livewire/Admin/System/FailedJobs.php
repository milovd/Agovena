<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

final class FailedJobs extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('jobs.view');
    }

    public function retry(string $uuid): void
    {
        $this->authorize('jobs.manage');
        abort_unless(Str::isUuid($uuid), 404);
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        session()->flash('status', __('admin.failed_jobs.retried'));
    }

    public function forget(int $id): void
    {
        $this->authorize('jobs.manage');
        abort_unless(DB::table('failed_jobs')->where('id', $id)->exists(), 404);
        DB::table('failed_jobs')->where('id', $id)->delete();
        session()->flash('status', __('admin.failed_jobs.deleted'));
    }

    public function render(AdminRegistrar $admin)
    {
        $jobs = DB::table('failed_jobs')->orderByDesc('id')->paginate(20);
        $jobs->getCollection()->transform(function (object $job): object {
            $job->exception_preview = $this->preview((string) $job->exception);

            return $job;
        });

        return view('livewire.admin.system.failed-jobs', [
            'jobs' => $jobs,
        ])->layout('layouts.admin', [
            'title' => __('admin.failed_jobs.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function preview(string $exception): string
    {
        $line = strtok(str_replace(["\r\n", "\r"], "\n", $exception), "\n") ?: $exception;

        return mb_substr($line, 0, 240);
    }
}
