<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content;

use App\Agovena\Content\DeletePage;
use App\Agovena\Content\PageSlug;
use App\Agovena\Content\SavePage;
use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPublicationFilter(): void
    {
        $this->resetPage();
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

    public function render()
    {
        $this->authorize('pages.view');

        $search = trim($this->search);

        return view('livewire.admin.content.pages-index', [
            'pages' => Page::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')))
                ->when(in_array($this->publicationFilter, ['draft', 'published'], true),
                    fn ($query) => $query->where('status', $this->publicationFilter))
                ->orderBy('title')->orderBy('id')->paginate(20),
        ])->layout('layouts.admin', [
            'title' => __('admin.content.pages.title'),
        ]);
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
