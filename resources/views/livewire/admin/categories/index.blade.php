<div class="admin-page">
    <x-ag.page-header :heading="__('admin.categories.title')" :lede="__('admin.categories.lede')">
        <x-slot:actions>
            @can('categories.create')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.categories.add') }}</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="category-search">{{ __('admin.categories.search_label') }}</label>
                <input id="category-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.categories.search_placeholder') }}">
            </div>
        </div>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="ag-section ag-form ag-form--constrained" novalidate>
            <header class="ag-section__header" style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap; align-items:flex-start;">
                <div>
                    <h3 class="ag-section__title">{{ $editingId ? __('admin.categories.edit') : __('admin.categories.new') }}</h3>
                </div>
                @if ($editingId)
                    @if ($is_active && filled($slug))
                        <a class="ag-btn ag-btn--secondary ag-btn--sm" href="{{ route('storefront.category', $slug) }}" target="_blank" rel="noopener">
                            <x-ag.icon name="eye" :size="16" />
                            {{ __('admin.categories.preview') }}
                        </a>
                    @else
                        <span class="ag-btn ag-btn--secondary ag-btn--sm is-disabled" title="{{ __('admin.categories.preview_disabled') }}" aria-disabled="true">
                            <x-ag.icon name="eye" :size="16" />
                            {{ __('admin.categories.preview') }}
                        </span>
                    @endif
                @endif
            </header>
            <div class="ag-section__body">
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="category-name">{{ __('common.name') }}</label>
                        <input id="category-name" class="ag-input" type="text" wire:model="name" required>
                        @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="category-slug">{{ __('common.slug') }}</label>
                        <input id="category-slug" class="ag-input" type="text" wire:model="slug">
                        @error('slug') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field ag-grid__span-2">
                        <label class="ag-field__label" for="category-description">{{ __('common.description') }}</label>
                        <textarea id="category-description" class="ag-input ag-input--area" rows="3" wire:model="description"></textarea>
                        @error('description') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="category-parent">{{ __('admin.categories.parent') }}</label>
                        <select id="category-parent" class="ag-select" wire:model="parent_id">
                            <option value="">{{ __('admin.categories.parent_none') }}</option>
                            @foreach ($parentOptions as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        <p class="ag-field__hint">{{ __('admin.categories.parent_hint') }}</p>
                        @error('parent_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field" style="display:flex; align-items:flex-end;">
                        <x-ag.switch id="category-active" wire:model="is_active" :label="__('common.active')" />
                    </div>
                    <div class="ag-field ag-grid__span-2">
                        <x-ag.file-upload
                            id="category-image"
                            :label="__('common.image')"
                            :hint="__('admin.categories.image_hint')"
                            :button-label="__('admin.categories.upload_image')"
                            :replace-label="__('admin.categories.replace_image')"
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
                    <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">{{ __('common.save') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </form>
    @endif

    @if ($categories->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search ? __('admin.categories.empty.filtered_title') : __('admin.categories.empty.title') }}</p>
            <p class="ag-empty__text">{{ $search ? __('admin.categories.empty.filtered_text') : __('admin.categories.empty.text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table ag-table--categories">
                <thead>
                    <tr>
                        <th scope="col" class="ag-table__thumb-col"><span class="visually-hidden">{{ __('common.image') }}</span></th>
                        <th scope="col">{{ __('common.name') }}</th>
                        <th scope="col" class="ag-table__col--md">{{ __('admin.categories.parent_column') }}</th>
                        <th scope="col" class="ag-table__col--lg">{{ __('common.slug') }}</th>
                        <th scope="col">{{ __('common.products') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
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
                            <td class="ag-table__col--md">{{ $category->parent?->name ?? __('common.em_dash') }}</td>
                            <td class="ag-table__col--lg"><span class="ag-muted">{{ $category->slug }}</span></td>
                            <td>{{ $category->products_count }}</td>
                            <td>
                                <span @class(['ag-badge', 'ag-badge--success' => $category->is_active, 'ag-badge--muted' => ! $category->is_active])>
                                    {{ $category->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td class="ag-table__actions">
                                <div class="ag-row-actions">
                                    @can('categories.update')
                                        <button type="button" class="ag-icon-btn" wire:click="edit({{ $category->id }})" title="{{ __('admin.categories.actions.edit') }}" aria-label="{{ __('admin.categories.actions.edit_aria', ['name' => $category->name]) }}">
                                            <x-ag.icon name="pencil" :size="16" />
                                        </button>
                                    @endcan
                                    @can('categories.delete')
                                        <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="confirmDelete({{ $category->id }})" title="{{ __('admin.categories.actions.delete') }}" aria-label="{{ __('admin.categories.actions.delete_aria', ['name' => $category->name]) }}">
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
                <h3 id="delete-category-title" class="ag-modal__title">{{ __('admin.categories.delete.title', ['name' => $confirmingCategory->name]) }}</h3>
                @if ($confirmingCategory->products_count > 0)
                    <p class="ag-modal__text">{{ trans_choice('admin.categories.delete.has_products', $confirmingCategory->products_count, ['count' => $confirmingCategory->products_count]) }}</p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.close') }}</button>
                    </div>
                @elseif ($confirmingCategory->children_count > 0)
                    <p class="ag-modal__text">{{ trans_choice('admin.categories.delete.has_children', $confirmingCategory->children_count, ['count' => $confirmingCategory->children_count]) }}</p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.close') }}</button>
                    </div>
                @else
                    <p class="ag-modal__text">{{ __('admin.categories.delete.text') }}</p>
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteCategory">{{ __('admin.categories.delete.confirm') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.cancel') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
