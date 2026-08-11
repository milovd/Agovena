<div class="admin-page">
    <div class="admin-page__header">
        <h2 class="admin-page__heading">{{ $mode === 'create' ? 'Create product' : 'Edit product' }}</h2>
        <a class="ag-btn ag-btn--ghost" href="{{ route('admin.products.index') }}">Back</a>
    </div>

    <form wire:submit="save" class="admin-panel" novalidate>
        <div class="ag-field">
            <label class="ag-field__label" for="name">Name</label>
            <input id="name" class="ag-input" type="text" wire:model="name" required>
            @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="slug">Slug</label>
            <input id="slug" class="ag-input" type="text" wire:model="slug" aria-describedby="slug-hint">
            <p id="slug-hint" class="ag-field__hint">Leave blank to generate from name.</p>
            @error('slug') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="description">Description</label>
            <textarea id="description" class="ag-input ag-input--area" rows="4" wire:model="description"></textarea>
            @error('description') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="status">Status</label>
            <select id="status" class="ag-select" wire:model="status">
                <option value="draft">Draft</option>
                <option value="active">Active (published)</option>
            </select>
            <p class="ag-field__hint">Draft products are not listable or purchasable on the storefront.</p>
            @error('status') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="price_amount">Price (minor units)</label>
            <input id="price_amount" class="ag-input" type="number" min="0" step="1" wire:model="price_amount" required aria-describedby="price-hint">
            <p id="price-hint" class="ag-field__hint">Integer minor units only (e.g. 1999 = 19.99). No floats.</p>
            @error('price_amount') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="currency">Currency</label>
            <input id="currency" class="ag-input" type="text" maxlength="3" wire:model="currency" required>
            @error('currency') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="category_id">Category</label>
            <select id="category_id" class="ag-select" wire:model="category_id">
                <option value="">— None —</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">
            {{ $mode === 'create' ? 'Create' : 'Save' }}
        </button>
    </form>
</div>
