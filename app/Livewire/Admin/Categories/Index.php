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
            'is_active' => ['boolean'],
        ]);

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
            'categories' => Category::query()->orderBy('name')->paginate(20),
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
        $this->is_active = true;
        $this->resetValidation();
    }
}
