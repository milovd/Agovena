<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content;

use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

final class PagesIndex extends Component
{
    use WithPagination;

    public string $title = '';

    public string $slug = '';

    public string $body = '';

    public string $status = 'draft';

    public ?int $editingId = null;

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
                'alpha_dash',
                Rule::unique('pages', 'slug')->ignore($this->editingId),
                Rule::notIn(['admin', 'cart', 'checkout', 'products', 'categories', 'orders', 'install']),
            ],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        if ($this->editingId !== null) {
            Page::query()->whereKey($this->editingId)->update($data);
        } else {
            Page::query()->create($data);
        }

        $this->resetForm();
        session()->flash('status', 'Page saved.');
    }

    public function delete(int $id): void
    {
        $this->authorize('pages.manage');
        Page::query()->whereKey($id)->delete();
        session()->flash('status', 'Page deleted.');
    }

    public function render()
    {
        $this->authorize('pages.view');

        return view('livewire.admin.content.pages-index', [
            'pages' => Page::query()->orderBy('title')->paginate(20),
        ])->layout('layouts.admin', [
            'title' => 'Pages',
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
