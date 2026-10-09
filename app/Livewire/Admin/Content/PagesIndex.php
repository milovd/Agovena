<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content;

use App\Agovena\Content\DeletePage;
use App\Agovena\Content\PageSlug;
use App\Agovena\Content\SavePage;
use App\Agovena\Content\SetSelectedPageStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

final class PagesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $publicationFilter = '';

    public string $title = '';

    public string $slug = '';

    public string $body = '';

    public string $status = 'draft';

    public ?int $editingId = null;

    /** @var array<int, mixed> */
    public array $selectedPageIds = [];

    /** @var array{updated: int, unchanged: int, skipped: int, failed: int}|null */
    #[Locked]
    public ?array $bulkResult = null;

    private const MAX_BULK_SELECTION = 500;

    public function updatedSearch(): void
    {
        $this->selectedPageIds = [];
        $this->resetPage();
    }

    public function updatedPublicationFilter(): void
    {
        $this->selectedPageIds = [];
        $this->resetPage();
    }

    public function updatedPaginators(): void
    {
        $this->selectedPageIds = [];
    }

    public function create(): void
    {
        $this->authorize('pages.manage');
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $this->authorize('pages.manage');
        $page = Page::query()->findOrFail($id);
        $this->editingId = $page->id;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->body = (string) $page->body;
        $this->status = $page->status;
    }

    public function save(): void
    {
        $this->authorize('pages.manage');

        if ($this->slug === '' && $this->title !== '') {
            $this->slug = Str::slug($this->title);
        }

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:'.PageSlug::VALIDATION_PATTERN,
                Rule::unique('pages', 'slug')->ignore($this->editingId),
            ],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        if ($this->editingId !== null) {
            app(SavePage::class)->update($this->editingId, $data);
        } else {
            app(SavePage::class)->create($data);
        }

        $this->resetForm();
        session()->flash('status', __('admin.content.pages.saved'));
    }

    public function delete(int $id): void
    {
        $this->authorize('pages.manage');
        app(DeletePage::class)->handle($id);
        session()->flash('status', __('admin.content.pages.deleted'));
    }

    public function selectCurrentPage(): void
    {
        $this->authorize('pages.manage');
        $this->selectedPageIds = $this->visiblePages()->getCollection()->pluck('id')->all();
    }

    public function bulkSetStatus(string $status): void
    {
        $this->authorize('pages.manage');
        if (! in_array($status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['bulkStatus' => __('admin.content.pages.bulk_invalid_status')]);
        }

        // Reject the raw payload before deduplication so forged duplicate arrays cannot bypass the limit.
        if (count($this->selectedPageIds) > self::MAX_BULK_SELECTION) {
            throw ValidationException::withMessages(['selectedPageIds' => __('admin.content.pages.bulk_limit')]);
        }

        $requested = array_unique(array_map(
            static fn (mixed $id): string => is_int($id) || is_string($id) ? (string) $id : '',
            $this->selectedPageIds,
        ));
        $visibleIds = $this->visiblePages()->getCollection()->pluck('id')->all();
        $ids = array_values(array_filter($visibleIds, static fn (int $id): bool => in_array((string) $id, $requested, true)));

        if ($requested === [] || $requested === ['']) {
            throw ValidationException::withMessages(['selectedPageIds' => __('admin.content.pages.bulk_empty')]);
        }

        $result = app(SetSelectedPageStatus::class)->handle($ids, $status);
        $result['skipped'] += count($requested) - count($ids);
        $this->selectedPageIds = [];
        $this->bulkResult = $result;
    }

    public function render()
    {
        $this->authorize('pages.view');

        return view('livewire.admin.content.pages-index', [
            'pages' => $this->visiblePages(),
        ])->layout('layouts.admin', [
            'title' => __('admin.content.pages.title'),
        ]);
    }

    /** @return LengthAwarePaginator<int, Page> */
    private function visiblePages(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Page::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')))
            ->when(in_array($this->publicationFilter, ['draft', 'published'], true),
                fn (Builder $query) => $query->where('status', $this->publicationFilter))
            ->orderBy('title')->orderBy('id')->paginate(20);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->slug = '';
        $this->body = '';
        $this->status = 'draft';
    }
}
