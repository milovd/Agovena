<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">Pages</h2>
            <p class="admin-page__lede">Merchant content pages for About, Terms, Privacy, and more.</p>
        </div>
    </header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-split">
        <div class="admin-panel">
            <h3 class="admin-panel__title">{{ $editingId ? 'Edit page' : 'New page' }}</h3>
            <form class="ag-form" wire:submit="save">
                <div class="ag-field">
                    <label class="ag-field__label" for="page-title">Title</label>
                    <input id="page-title" class="ag-input" type="text" wire:model="title" required>
                    @error('title') <p class="ag-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="page-slug">Slug</label>
                    <input id="page-slug" class="ag-input" type="text" wire:model="slug">
                    @error('slug') <p class="ag-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="page-body">Body</label>
                    <textarea id="page-body" class="ag-input" rows="8" wire:model="body"></textarea>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="page-status">Status</label>
                    <select id="page-status" class="ag-select" wire:model="status">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <div class="ag-toolbar">
                    <button type="submit" class="ag-btn ag-btn--primary">Save</button>
                    @if ($editingId)
                        <button type="button" class="ag-btn" wire:click="create">Cancel</button>
                    @endif
                </div>
            </form>
        </div>

        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pages as $page)
                        <tr wire:key="page-{{ $page->id }}">
                            <td>
                                <strong>{{ $page->title }}</strong>
                                <div class="ag-muted">/{{ $page->slug }}</div>
                            </td>
                            <td><span class="ag-badge">{{ $page->status }}</span></td>
                            <td>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="edit({{ $page->id }})">Edit</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="delete({{ $page->id }})" wire:confirm="Delete this page?">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="ag-empty" role="status">
                                    <p class="ag-empty__title">No pages yet</p>
                                    <p class="ag-empty__text">Create About, Terms, or Privacy to link from the footer.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $pages->links() }}
        </div>
    </div>
</div>
