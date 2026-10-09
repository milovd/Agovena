<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content;

use App\Agovena\Content\PageBodySanitizer;
use App\Agovena\Content\PageSlug;
use App\Agovena\Content\SavePage;
use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class PageForm extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $slug = '';

    public string $body = '';

    public string $status = 'draft';

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public string $imageAlt = '';

    #[Locked]
    public ?int $pageId = null;

    public function mount(?Page $page = null): void
    {
        $this->authorize('pages.manage');

        if ($page !== null) {
            $this->pageId = $page->id;
            $this->title = $page->title;
            $this->slug = $page->slug;
            $this->body = ($page->body_format ?? 'plain') === 'html'
                ? (string) $page->body
                : app(PageBodySanitizer::class)->fromPlainText((string) $page->body);
            $this->status = $page->status;
        }
    }

    public function save(): void
    {
        $this->authorize('pages.manage');

        if ($this->slug === '' && $this->title !== '') {
            $this->slug = Str::slug($this->title);
        }

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:'.PageSlug::VALIDATION_PATTERN, Rule::unique('pages', 'slug')->ignore($this->pageId)],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        $data['body_format'] = 'html';

        if ($this->pageId === null) {
            app(SavePage::class)->create($data);
        } else {
            abort_if(app(SavePage::class)->update($this->pageId, $data) === null, 404);
        }
        session()->flash('status', __('admin.content.pages.saved'));
        $this->redirectRoute('admin.appearance.pages');
    }

    public function uploadImage(): void
    {
        $this->authorize('pages.manage');
        $this->imageAlt = trim($this->imageAlt);

        $this->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'imageAlt' => ['required', 'string', 'max:160'],
        ]);

        if (! $this->image instanceof TemporaryUploadedFile) {
            return;
        }

        $path = $this->image->store('pages', 'public');
        if (! is_string($path) || preg_match('~\Apages/[A-Za-z0-9]{40}\.(?:jpg|jpeg|png|webp|gif)\z~i', $path) !== 1) {
            throw new \RuntimeException('Page image storage failed.');
        }

        $this->dispatch('page-image-uploaded', url: '/storage/'.$path, alt: $this->imageAlt);
        $this->reset('image', 'imageAlt');
    }

    public function render()
    {
        $this->authorize('pages.manage');

        return view('livewire.admin.content.page-form')->layout('layouts.admin', [
            'title' => __('admin.content.pages.'.($this->pageId === null ? 'new' : 'edit')),
        ]);
    }
}
