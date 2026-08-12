<div class="admin-page admin-page--form">
    <x-ag.page-header
        :heading="$mode === 'create' ? 'Create product' : 'Edit product'"
        :lede="$mode === 'create' ? 'Add a catalog product. Photos can be uploaded after saving.' : 'Update product details, media, and publishing status.'"
    >
        <x-slot:back>
            <x-ag.back :href="route('admin.products.index')" label="Products" />
        </x-slot:back>
        <x-slot:actions>
            @if ($mode === 'edit')
                @if ($product->status->value === 'active')
                    <a
                        class="ag-btn ag-btn--secondary"
                        href="{{ route('storefront.product', $product->slug) }}"
                        target="_blank"
                        rel="noopener"
                    >
                        <x-ag.icon name="eye" :size="16" />
                        Preview product
                    </a>
                @else
                    <span
                        class="ag-btn ag-btn--secondary is-disabled"
                        title="Set status to Active to preview on the storefront"
                        aria-disabled="true"
                    >
                        <x-ag.icon name="eye" :size="16" />
                        Preview product
                    </span>
                @endif
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <form id="product-form" wire:submit="save" class="ag-form ag-form--product" novalidate>
        <section class="ag-section" aria-labelledby="section-basic">
            <header class="ag-section__header">
                <h3 id="section-basic" class="ag-section__title">Basic information</h3>
                <p class="ag-section__lede">Core identity used across Admin and the storefront.</p>
            </header>
            <div class="ag-section__body">
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field ag-grid__span-2">
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
                        <label class="ag-field__label" for="sku">SKU</label>
                        <input id="sku" class="ag-input" type="text" wire:model="sku" aria-describedby="sku-hint">
                        <p id="sku-hint" class="ag-field__hint">Optional unique stock-keeping code.</p>
                        @error('sku') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="status">Status</label>
                        <select id="status" class="ag-select" wire:model="status">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                        </select>
                        <p class="ag-field__hint">Draft products are not listable or purchasable.</p>
                        @error('status') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="category_id">Category</label>
                        <select id="category_id" class="ag-select" wire:model="category_id">
                            <option value="">None</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="ag-section" aria-labelledby="section-description">
            <header class="ag-section__header">
                <h3 id="section-description" class="ag-section__title">Description</h3>
                <p class="ag-section__lede">Copy shown on the product page.</p>
            </header>
            <div class="ag-section__body">
                <div class="ag-field">
                    <label class="ag-field__label" for="subtitle">Short description</label>
                    <input id="subtitle" class="ag-input" type="text" wire:model="subtitle" aria-describedby="subtitle-hint">
                    <p id="subtitle-hint" class="ag-field__hint">One-line summary under the title. Falls back to a trimmed details text when empty.</p>
                    @error('subtitle') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="description">Details</label>
                    <textarea id="description" class="ag-input ag-input--area" rows="6" wire:model="description"></textarea>
                    @error('description') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-switch-row">
                    <x-ag.switch id="show_details" wire:model="show_details" label="Show Details tab" />
                    <x-ag.switch id="show_specifications" wire:model="show_specifications" label="Show specifications table" />
                </div>
                <div class="ag-field">
                    <div class="ag-field__label-row">
                        <label class="ag-field__label">Specifications</label>
                        <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="addSpecRow">Add row</button>
                    </div>
                    <p class="ag-field__hint">Optional label / value rows. Leave blank to skip.</p>
                    <div class="ag-spec-rows">
                        @foreach ($specRows as $index => $row)
                            <div class="ag-spec-rows__row" wire:key="spec-{{ $index }}">
                                <input class="ag-input" type="text" placeholder="Label" wire:model="specRows.{{ $index }}.label" aria-label="Spec label {{ $index + 1 }}">
                                <input class="ag-input" type="text" placeholder="Value" wire:model="specRows.{{ $index }}.value" aria-label="Spec value {{ $index + 1 }}">
                                <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="removeSpecRow({{ $index }})" aria-label="Remove row">Remove</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="ag-section" aria-labelledby="section-pricing">
            <header class="ag-section__header">
                <h3 id="section-pricing" class="ag-section__title">Pricing</h3>
                <p class="ag-section__lede">Server-authoritative amounts in minor units.</p>
            </header>
            <div class="ag-section__body">
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="price">Price</label>
                        <input
                            id="price"
                            class="ag-input"
                            type="text"
                            inputmode="decimal"
                            wire:model="price"
                            required
                            aria-describedby="price-hint"
                            placeholder="45.00"
                        >
                        <p id="price-hint" class="ag-field__hint">Enter the amount customers pay, e.g. 45 or 45.00 (comma also works).</p>
                        @error('price') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="currency">Currency</label>
                        @if ($currencies->isNotEmpty())
                            <select id="currency" class="ag-select" wire:model="currency">
                                @foreach ($currencies as $currencyOption)
                                    <option value="{{ $currencyOption->code }}">{{ $currencyOption->code }} — {{ $currencyOption->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="currency" class="ag-input" type="text" maxlength="3" wire:model="currency" required>
                        @endif
                        @error('currency') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </section>
    </form>

    @if ($mode === 'edit')
        <section class="ag-section ag-form--product" aria-labelledby="section-media">
            <header class="ag-section__header">
                <h3 id="section-media" class="ag-section__title">Media</h3>
                <p class="ag-section__lede">Primary image and gallery used on the product page.</p>
            </header>
            <div class="ag-section__body">
                <x-ag.file-upload
                    id="product-uploads"
                    label="Add photos"
                    hint="JPEG, PNG, WebP, or GIF. Max 4 MB per image."
                    multiple
                    button-label="Upload photos"
                    replace-label="Upload more"
                    loading-target="uploads"
                    wire:model="uploads"
                >
                    @error('uploads') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    @error('uploads.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </x-ag.file-upload>

                @if ($galleryImages->isNotEmpty())
                    <ul class="ag-gallery-admin" role="list">
                        @foreach ($galleryImages as $image)
                            @php $isPrimary = $product->image_path === $image->path; @endphp
                            <li class="ag-media-tile {{ $isPrimary ? 'is-primary' : '' }}" wire:key="img-{{ $image->id }}">
                                <div class="ag-media-tile__preview">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="" width="112" height="112">
                                    @if ($isPrimary)
                                        <span class="ag-media-tile__badge">Primary</span>
                                    @endif
                                </div>
                                <div class="ag-media-tile__toolbar">
                                    <div class="ag-media-tile__tools">
                                        <button type="button" class="ag-icon-btn" wire:click="moveImage({{ $image->id }}, 'up')" title="Move earlier" aria-label="Move photo earlier">
                                            <x-ag.icon name="chevron-up" :size="16" />
                                        </button>
                                        <button type="button" class="ag-icon-btn" wire:click="moveImage({{ $image->id }}, 'down')" title="Move later" aria-label="Move photo later">
                                            <x-ag.icon name="chevron-down" :size="16" />
                                        </button>
                                    </div>
                                    <div
                                        class="ag-menu"
                                        x-data="{ open: false }"
                                        @keydown.escape.window="open = false"
                                        @click.outside="open = false"
                                    >
                                        <button
                                            type="button"
                                            class="ag-icon-btn"
                                            @click="open = !open"
                                            :aria-expanded="open.toString()"
                                            aria-haspopup="menu"
                                            title="Photo actions"
                                            aria-label="Photo actions"
                                        >
                                            <x-ag.icon name="more-horizontal" :size="16" />
                                        </button>
                                        <div class="ag-menu__panel" x-show="open" x-cloak role="menu">
                                            @unless ($isPrimary)
                                                <button type="button" class="ag-menu__item" role="menuitem" wire:click="setPrimaryImage({{ $image->id }})">
                                                    Set as primary
                                                </button>
                                            @endunless
                                            <button
                                                type="button"
                                                class="ag-menu__item ag-menu__item--danger"
                                                role="menuitem"
                                                wire:click="removeImage({{ $image->id }})"
                                                wire:confirm="Remove this photo?"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="ag-empty ag-empty--compact" role="status">No photos yet.</p>
                @endif
            </div>
        </section>

        @can('products.delete')
            <div class="ag-form--product">
                <x-ag.danger-zone
                    title="Delete product"
                    :description="$isReferenced
                        ? 'This product appears on historical orders and cannot be permanently deleted. Set status to Draft so it is no longer sold.'
                        : 'Permanently removes this product and its media. Prefer Draft if you may need it later.'"
                >
                    @if ($isReferenced)
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="setDraft">
                            Set as draft
                        </button>
                    @else
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="confirmDelete">
                            Delete permanently
                        </button>
                    @endif
                </x-ag.danger-zone>
            </div>
        @endcan

        @if ($confirmingDelete)
            <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="delete-edit-title">
                <div class="ag-modal__backdrop" wire:click="cancelDelete"></div>
                <div class="ag-modal__panel">
                    <h3 id="delete-edit-title" class="ag-modal__title">Delete {{ $product->name }}?</h3>
                    <p class="ag-modal__text">This permanently deletes the product and its photos. This cannot be undone.</p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteProduct">Delete permanently</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">Cancel</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <p class="ag-field__hint">After creating the product you can upload gallery photos on the edit screen.</p>
    @endif

    <div class="ag-form__sticky ag-form__sticky--page" role="group" aria-label="Form actions">
        <a class="ag-btn ag-btn--secondary" href="{{ route('admin.products.index') }}">Cancel</a>
        <button type="submit" form="product-form" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $mode === 'create' ? 'Create product' : 'Save changes' }}</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </div>
</div>
