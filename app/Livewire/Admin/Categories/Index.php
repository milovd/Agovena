<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Categories;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\DeleteCategory;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public ?int $parent_id = null;

    public bool $is_active = true;

    public string $search = '';

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public ?string $existingImagePath = null;

    public bool $removeExistingImage = false;

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('categories.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('categories.create');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $categoryId): void
    {
        $this->authorize('categories.update');
        $category = Category::query()->findOrFail($categoryId);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = (string) $category->description;
        $this->parent_id = $category->parent_id;
        $this->is_active = $category->is_active;
        $this->existingImagePath = $category->image_path;
        $this->removeExistingImage = false;
        $this->image = null;
        $this->showForm = true;
    }

    public function save(): void
    {
        if ($this->editingId === null) {
            $this->authorize('categories.create');
        } else {
            $this->authorize('categories.update');
        }

        if ($this->slug === '' && $this->name !== '') {
            $this->slug = Str::slug($this->name);
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($this->editingId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id'),
                Rule::notIn(array_filter([$this->editingId])),
            ],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
        ]);

        if ($data['parent_id'] !== null) {
            $parent = Category::query()->find($data['parent_id']);
            if ($parent?->parent_id !== null) {
                $this->addError('parent_id', __('admin.categories.validation.one_level'));

                return;
            }
        }

        $imagePath = $this->existingImagePath;
        if ($this->removeExistingImage) {
            if (filled($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = null;
        }

        if ($this->image instanceof TemporaryUploadedFile) {
            if (filled($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->image->store('categories', 'public');
        }

        $payload = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?: null,
            'parent_id' => $data['parent_id'],
            'is_active' => (bool) $data['is_active'],
            'image_path' => $imagePath,
        ];

        if ($this->editingId === null) {
            Category::query()->create($payload);
            session()->flash('status', __('admin.categories.flash.created'));
        } else {
            Category::query()->whereKey($this->editingId)->update($payload);
            session()->flash('status', __('admin.categories.flash.updated'));
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function clearImage(): void
    {
        $this->removeExistingImage = true;
        $this->image = null;
        $this->existingImagePath = null;
    }

    public function confirmDelete(int $categoryId): void
    {
        $this->authorize('categories.delete');
        $this->confirmingDeleteId = $categoryId;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function deleteCategory(DeleteCategory $delete): void
    {
        $this->authorize('categories.delete');

        if ($this->confirmingDeleteId === null) {
            return;
        }

        $category = Category::query()->findOrFail($this->confirmingDeleteId);

        try {
            $delete->handle($category);
            session()->flash('status', __('admin.categories.flash.deleted'));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['category'][0] ?? $e->getMessage());
        }

        $this->confirmingDeleteId = null;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        $query = Category::query()->with('parent')->withCount('products');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)->orWhere('slug', 'like', $term);
            });
        }

        return view('livewire.admin.categories.index', [
            'categories' => $query->orderBy('name')->paginate(20),
            'parentOptions' => Category::query()
                ->whereNull('parent_id')
                ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'confirmingCategory' => $this->confirmingDeleteId
                ? Category::query()->withCount(['products', 'children'])->find($this->confirmingDeleteId)
                : null,
        ])->layout('layouts.admin', [
            'title' => __('admin.categories.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->parent_id = null;
        $this->is_active = true;
        $this->image = null;
        $this->existingImagePath = null;
        $this->removeExistingImage = false;
        $this->resetValidation();
    }
}
