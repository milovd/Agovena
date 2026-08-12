<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">{{ __('admin.content.pages.title') }}</h2>
            <p class="admin-page__lede">{{ __('admin.content.pages.lede') }}</p>
        </div>
    </header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-split">
        <div class="admin-panel">
            <h3 class="admin-panel__title">{{ $editingId ? __('admin.content.pages.edit') : __('admin.content.pages.new') }}</h3>
            <form class="ag-form" wire:submit="save">
                <div class="ag-field">
                    <label class="ag-field__label" for="page-title">{{ __('common.title') }}</label>
                    <input id="page-title" class="ag-input" type="text" wire:model="title" required>
                    @error('title') <p class="ag-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="page-slug">{{ __('admin.content.pages.slug') }}</label>
                    <input id="page-slug" class="ag-input" type="text" wire:model="slug">
                    @error('slug') <p class="ag-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="page-body">{{ __('admin.content.pages.body') }}</label>
                    <textarea id="page-body" class="ag-input" rows="8" wire:model="body"></textarea>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="page-status">{{ __('common.status') }}</label>
                    <select id="page-status" class="ag-select" wire:model="status">
                        <option value="draft">{{ __('admin.content.pages.draft') }}</option>
                        <option value="published">{{ __('admin.content.pages.published') }}</option>
                    </select>
                </div>
                <div class="ag-toolbar">
                    <button type="submit" class="ag-btn ag-btn--primary">{{ __('common.save') }}</button>
                    @if ($editingId)
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="create">{{ __('common.cancel') }}</button>
                    @endif
                </div>
            </form>
        </div>

        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('common.title') }}</th>
                        <th>{{ __('common.status') }}</th>
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
                            <td><span class="ag-badge">{{ __('admin.content.pages.'.$page->status) }}</span></td>
                            <td>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="edit({{ $page->id }})">{{ __('common.edit') }}</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="delete({{ $page->id }})" wire:confirm="{{ __('admin.content.pages.delete_confirm') }}">{{ __('common.delete') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="ag-empty" role="status">
                                    <p class="ag-empty__title">{{ __('admin.content.pages.empty_title') }}</p>
                                    <p class="ag-empty__text">{{ __('admin.content.pages.empty_text') }}</p>
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
