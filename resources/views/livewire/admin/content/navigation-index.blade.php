<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">Navigation</h2>
            <p class="admin-page__lede">Menus consumed by the active Theme header and footer.</p>
        </div>
    </header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-toolbar" role="tablist" aria-label="Menus">
        @foreach ($menus as $m)
            <button
                type="button"
                class="ag-btn {{ $m->handle === $selectedHandle ? 'ag-btn--primary' : '' }}"
                wire:click="selectMenu('{{ $m->handle }}')"
            >{{ $m->name }}</button>
        @endforeach
    </div>

    <div class="ag-split">
        <div class="admin-panel">
            <h3 class="admin-panel__title">Add item to {{ $menu->name }}</h3>
            <form class="ag-form" wire:submit="addItem">
                <div class="ag-field">
                    <label class="ag-field__label" for="nav-label">Label</label>
                    <input id="nav-label" class="ag-input" type="text" wire:model="label" required>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="nav-type">Type</label>
                    <select id="nav-type" class="ag-select" wire:model.live="type">
                        <option value="url">Custom URL</option>
                        <option value="page">Page</option>
                        <option value="category">Category</option>
                    </select>
                </div>
                @if ($type === 'url')
                    <div class="ag-field">
                        <label class="ag-field__label" for="nav-url">URL</label>
                        <input id="nav-url" class="ag-input" type="text" wire:model="url" placeholder="/ or https://…">
                    </div>
                @elseif ($type === 'page')
                    <div class="ag-field">
                        <label class="ag-field__label" for="nav-page">Page</label>
                        <select id="nav-page" class="ag-select" wire:model="page_id">
                            <option value="">Select…</option>
                            @foreach ($pages as $page)
                                <option value="{{ $page->id }}">{{ $page->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="ag-field">
                        <label class="ag-field__label" for="nav-cat">Category</label>
                        <select id="nav-cat" class="ag-select" wire:model="category_id">
                            <option value="">Select…</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="ag-btn ag-btn--primary">Add item</button>
            </form>
        </div>

        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Type</th>
                        <th>Target</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menu->allItems as $item)
                        <tr wire:key="menu-item-{{ $item->id }}">
                            <td>{{ $item->label }}</td>
                            <td>{{ $item->type }}</td>
                            <td class="ag-muted">
                                @if ($item->type === 'url')
                                    {{ $item->url }}
                                @elseif ($item->type === 'page')
                                    {{ $item->page?->title ?? '—' }}
                                @else
                                    {{ $item->category?->name ?? '—' }}
                                @endif
                            </td>
                            <td>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="deleteItem({{ $item->id }})" wire:confirm="Remove this item?">Remove</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ag-empty" role="status">
                                    <p class="ag-empty__title">No items</p>
                                    <p class="ag-empty__text">Add links, pages, or categories for this menu.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
