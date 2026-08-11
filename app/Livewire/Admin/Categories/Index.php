<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Categories;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public ?int $parent_id = null;

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('categories.view');
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
        ]);

        if ($data['parent_id'] !== null) {
            $parent = Category::query()->find($data['parent_id']);
            if ($parent?->parent_id !== null) {
                $this->addError('parent_id', 'Only one subcategory level is supported.');

                return;
            }
        }

        if ($this->editingId === null) {
            Category::query()->create($data);
            session()->flash('status', 'Category created.');
        } else {
            Category::query()->whereKey($this->editingId)->update($data);
            session()->flash('status', 'Category updated.');
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.categories.index', [
            'categories' => Category::query()->with('parent')->orderBy('name')->paginate(20),
            'parentOptions' => Category::query()
                ->whereNull('parent_id')
                ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->layout('layouts.admin', [
            'title' => 'Categories',
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
        $this->resetValidation();
    }
}
