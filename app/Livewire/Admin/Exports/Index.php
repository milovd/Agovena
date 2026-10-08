<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Exports;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Exports\ExportDatasetRegistry;
use App\Agovena\Exports\ExportFormat;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;

    public string $entity = '';

    public string $format = 'csv';

    public string $createdFrom = '';

    public string $createdTo = '';

    public function mount(ExportDatasetRegistry $registry): void
    {
        $this->authorize(ExportDatasetRegistry::PERMISSION);
        $this->entity = (string) array_key_first($registry->availableFor($this->staff()));
    }

    public function export(ExportDatasetRegistry $registry): void
    {
        $this->authorize(ExportDatasetRegistry::PERMISSION);

        $toRules = ['nullable', 'date_format:Y-m-d'];
        if ($this->createdFrom !== '') {
            $toRules[] = 'after_or_equal:createdFrom';
        }

        $this->validate([
            'entity' => ['required', 'string', Rule::in(array_keys($registry->availableFor($this->staff())))],
            'format' => ['required', 'string', Rule::enum(ExportFormat::class)],
            'createdFrom' => ['nullable', 'date_format:Y-m-d'],
            'createdTo' => $toRules,
        ], [], [
            'entity' => __('admin.exports.entity'),
            'format' => __('admin.exports.format'),
            'createdFrom' => __('admin.exports.created_from'),
            'createdTo' => __('admin.exports.created_to'),
        ]);

        $this->redirect(route('admin.exports.download', array_filter([
            'entity' => $this->entity,
            'format' => $this->format,
            'created_from' => $this->createdFrom,
            'created_to' => $this->createdTo,
        ], static fn (string $value): bool => $value !== '')));
    }

    public function render(AdminRegistrar $admin, ExportDatasetRegistry $registry)
    {
        $this->authorize(ExportDatasetRegistry::PERMISSION);

        $datasets = $registry->availableFor($this->staff());
        $selected = $datasets[$this->entity] ?? null;

        return view('livewire.admin.exports.index', [
            'entityOptions' => array_map(
                static fn (string $key): string => __('admin.exports.entities.'.$key),
                array_combine(array_keys($datasets), array_keys($datasets)),
            ),
            'formatOptions' => collect(ExportFormat::cases())
                ->mapWithKeys(static fn (ExportFormat $format): array => [$format->value => __('admin.exports.formats.'.$format->value)])
                ->all(),
            'columns' => $selected?->columns() ?? [],
        ])->layout('layouts.admin', [
            'title' => __('admin.exports.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function staff(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
