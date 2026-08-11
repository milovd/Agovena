<div class="admin-page">
    <div class="admin-page__header">
        <h2 class="admin-page__heading">Categories</h2>
        @can('categories.create')
            <button type="button" class="ag-btn ag-btn--primary" wire:click="create">Add category</button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ $editingId ? 'Edit category' : 'New category' }}</h3>
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
            <div class="ag-field">
                <label class="ag-field__label" for="category-description">Description</label>
                <textarea id="category-description" class="ag-input" rows="3" wire:model="description"></textarea>
                @error('description') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <label class="ag-check">
                <input type="checkbox" wire:model="is_active">
                <span>Active</span>
            </label>
            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary">Save</button>
                <button type="button" class="ag-btn ag-btn--ghost" wire:click="cancel">Cancel</button>
            </div>
        </form>
    @endif

    @if ($categories->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">No categories yet</p>
            <p class="ag-empty__text">Organize products with categories when you need them.</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Slug</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr wire:key="category-{{ $category->id }}">
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->slug }}</td>
                            <td><span class="ag-badge">{{ $category->is_active ? 'active' : 'inactive' }}</span></td>
                            <td>
                                @can('categories.update')
                                    <button type="button" class="ag-btn ag-btn--ghost" wire:click="edit({{ $category->id }})">Edit</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $categories->links() }}
    @endif
</div>
