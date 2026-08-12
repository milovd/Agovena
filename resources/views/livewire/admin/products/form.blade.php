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
            <label class="ag-field__label" for="subtitle">Subtitle</label>
            <input id="subtitle" class="ag-input" type="text" wire:model="subtitle" aria-describedby="subtitle-hint">
            <p id="subtitle-hint" class="ag-field__hint">Short line under the title on the product page. Falls back to a trimmed description when empty.</p>
            @error('subtitle') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="slug">Slug</label>
            <input id="slug" class="ag-input" type="text" wire:model="slug" aria-describedby="slug-hint">
            <p id="slug-hint" class="ag-field__hint">Leave blank to generate from name.</p>
            @error('slug') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="description">Details text</label>
            <textarea id="description" class="ag-input ag-input--area" rows="5" wire:model="description" aria-describedby="description-hint"></textarea>
            <p id="description-hint" class="ag-field__hint">Shown in the Details tab when “Show details” is enabled.</p>
            @error('description') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>

        <fieldset class="ag-fieldset">
            <legend class="ag-fieldset__legend">Product page sections</legend>
            <label class="ag-check">
                <input type="checkbox" wire:model="show_details">
                <span>Show Details tab (description text)</span>
            </label>
            <label class="ag-check">
                <input type="checkbox" wire:model="show_specifications">
                <span>Show specifications table</span>
            </label>
        </fieldset>

        <div class="ag-field">
            <div class="ag-field__label-row">
                <label class="ag-field__label">Specifications</label>
                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="addSpecRow">Add row</button>
            </div>
            <p class="ag-field__hint">Optional label / value rows for the Details table. Leave blank to skip.</p>
            <div class="ag-spec-rows">
                @foreach ($specRows as $index => $row)
                    <div class="ag-spec-rows__row" wire:key="spec-{{ $index }}">
                        <input class="ag-input" type="text" placeholder="Label" wire:model="specRows.{{ $index }}.label" aria-label="Spec label {{ $index + 1 }}">
                        <input class="ag-input" type="text" placeholder="Value" wire:model="specRows.{{ $index }}.value" aria-label="Spec value {{ $index + 1 }}">
                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="removeSpecRow({{ $index }})" aria-label="Remove row">Remove</button>
                    </div>
                @endforeach
            </div>
            @error('specRows') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
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

        <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="save">
            {{ $mode === 'create' ? 'Create' : 'Save' }}
        </button>
    </form>

    @if ($mode === 'edit')
        <section class="admin-panel" style="margin-top: 1.25rem;" aria-labelledby="gallery-heading">
            <h3 id="gallery-heading" class="admin-page__heading" style="font-size: 1.1rem;">Product photos</h3>
            <p class="ag-field__hint">Upload multiple images for the storefront gallery (arrows appear when there is more than one).</p>

            <div class="ag-field">
                <label class="ag-field__label" for="product-uploads">Add photos</label>
                <input id="product-uploads" class="ag-input" type="file" accept="image/*" multiple wire:model="uploads">
                <div wire:loading wire:target="uploads">Uploading…</div>
                @error('uploads') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                @error('uploads.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>

            @if ($galleryImages->isNotEmpty())
                <ul class="ag-gallery-admin" role="list">
                    @foreach ($galleryImages as $image)
                        <li class="ag-gallery-admin__item">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="" width="96" height="96">
                            <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="removeImage({{ $image->id }})" wire:confirm="Remove this photo?">Remove</button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="ag-field__hint">No photos yet.</p>
            @endif
        </section>
    @else
        <p class="ag-field__hint" style="margin-top: 1rem;">Create the product first, then add photos on the edit screen.</p>
    @endif
</div>
