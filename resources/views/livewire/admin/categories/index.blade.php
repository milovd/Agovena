<div class="admin-page">
    <x-ag.page-header heading="Categories" lede="Organize the catalog for browsing and merchandising.">
        <x-slot:actions>
            @can('categories.create')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">Add category</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="category-search">Search categories</label>
                <input id="category-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or slug">
            </div>
        </div>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="ag-section ag-form ag-form--constrained" novalidate>
            <header class="ag-section__header" style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; align-items:flex-start;">
                <div>
                    <h3 class="ag-section__title">{{ $editingId ? 'Edit category' : 'New category' }}</h3>
                </div>
                @if ($editingId)
                    @if ($is_active && filled($slug))
                        <a class="ag-btn ag-btn--secondary ag-btn--sm" href="{{ route('storefront.category', $slug) }}" target="_blank" rel="noopener">
                            <x-ag.icon name="eye" :size="16" />
                            Preview category
                        </a>
                    @else
                        <span class="ag-btn ag-btn--secondary ag-btn--sm is-disabled" title="Activate the category to preview on the storefront" aria-disabled="true">
                            <x-ag.icon name="eye" :size="16" />
                            Preview category
                        </span>
                    @endif
                @endif
            </header>
            <div class="ag-section__body">
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="category-name">Name</label>
                        <input id="category-name" class="ag-input" type="text" wire:model="name" required>
                        @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="category-slug">Slug</label>
                        <input id="category-slug" class="ag-input" type="text" wire:model="slug">
                        @error('slug') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field ag-grid__span-2">
                        <label class="ag-field__label" for="category-description">Description</label>
                        <textarea id="category-description" class="ag-input ag-input--area" rows="3" wire:model="description"></textarea>
                        @error('description') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="category-parent">Parent category</label>
                        <select id="category-parent" class="ag-select" wire:model="parent_id">
                            <option value="">None (top-level)</option>
                            @foreach ($parentOptions as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        <p class="ag-field__hint">Optional. One subcategory level only.</p>
                        @error('parent_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field" style="display:flex; align-items:flex-end;">
                        <x-ag.switch id="category-active" wire:model="is_active" label="Active" />
                    </div>
                    <div class="ag-field ag-grid__span-2">
                        <x-ag.file-upload
                            id="category-image"
                            label="Image"
                            hint="JPEG, PNG, WebP, or GIF. Max 4 MB."
                            button-label="Upload image"
                            replace-label="Replace"
                            :preview-url="($existingImagePath && ! $removeExistingImage) ? \Illuminate\Support\Facades\Storage::disk('public')->url($existingImagePath) : null"
                            remove-wire-click="clearImage"
                            loading-target="image"
                            wire:model="image"
                        >
                            @error('image') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </x-ag.file-upload>
                    </div>
                </div>
                <div class="ag-form__actions">
                    <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">Save</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">Cancel</button>
                </div>
            </div>
        </form>
    @endif

    @if ($categories->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search ? 'No matching categories' : 'No categories yet' }}</p>
            <p class="ag-empty__text">{{ $search ? 'Try a different search.' : 'Organize products with categories when you need them.' }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table ag-table--categories">
                <thead>
                    <tr>
                        <th scope="col" class="ag-table__thumb-col"><span class="visually-hidden">Image</span></th>
                        <th scope="col">Name</th>
                        <th scope="col" class="ag-table__col--md">Parent</th>
                        <th scope="col" class="ag-table__col--lg">Slug</th>
                        <th scope="col">Products</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}">
                            <td class="ag-table__thumb-col">
                                @if ($category->image_path)
                                    <img class="ag-thumb" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($category->image_path) }}" alt="" width="40" height="40" loading="lazy">
                                @else
                                    <span class="ag-thumb ag-thumb--empty" aria-hidden="true"></span>
                                @endif
                            </td>
                            <td><span class="ag-table__name">{{ $category->name }}</span></td>
                            <td class="ag-table__col--md">{{ $category->parent?->name ?? '—' }}</td>
                            <td class="ag-table__col--lg"><span class="ag-muted">{{ $category->slug }}</span></td>
                            <td>{{ $category->products_count }}</td>
                            <td>
                                <span @class(['ag-badge', 'ag-badge--success' => $category->is_active, 'ag-badge--muted' => ! $category->is_active])>
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="ag-table__actions">
                                <div class="ag-row-actions">
                                    @can('categories.update')
                                        <button type="button" class="ag-icon-btn" wire:click="edit({{ $category->id }})" title="Edit category" aria-label="Edit {{ $category->name }}">
                                            <x-ag.icon name="pencil" :size="16" />
                                        </button>
                                    @endcan
                                    @can('categories.delete')
                                        <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="confirmDelete({{ $category->id }})" title="Delete category" aria-label="Delete {{ $category->name }}">
                                            <x-ag.icon name="trash" :size="16" />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $categories->links() }}</div>
    @endif

    @if ($confirmingCategory)
        <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="delete-category-title">
            <div class="ag-modal__backdrop" wire:click="cancelDelete"></div>
            <div class="ag-modal__panel">
                <h3 id="delete-category-title" class="ag-modal__title">Delete {{ $confirmingCategory->name }}?</h3>
                @if ($confirmingCategory->products_count > 0)
                    <p class="ag-modal__text">This category still has {{ $confirmingCategory->products_count }} product(s). Reassign them or set the category inactive instead.</p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">Close</button>
                    </div>
                @elseif ($confirmingCategory->children_count > 0)
                    <p class="ag-modal__text">This category has {{ $confirmingCategory->children_count }} subcategor{{ $confirmingCategory->children_count === 1 ? 'y' : 'ies' }}. Remove or reassign them first.</p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">Close</button>
                    </div>
                @else
                    <p class="ag-modal__text">This permanently deletes the category. This cannot be undone.</p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteCategory">Delete permanently</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">Cancel</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
