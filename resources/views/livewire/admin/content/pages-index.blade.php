<div class="admin-page">
    <x-ag.page-header :heading="__('admin.content.pages.title')" :lede="__('admin.content.pages.lede')">
        <x-slot:actions>
            @can('pages.manage')
                <a class="ag-btn ag-btn--primary" href="{{ route('admin.appearance.pages.create') }}">{{ __('admin.content.pages.new') }}</a>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="page-search">{{ __('admin.content.pages.search_label') }}</label>
                <input id="page-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.content.pages.search_placeholder') }}">
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="page-publication">{{ __('common.status') }}</label>
                <select id="page-publication" class="ag-select" wire:model.live="publicationFilter">
                    <option value="">{{ __('admin.content.pages.all_statuses') }}</option>
                    <option value="draft">{{ __('admin.content.pages.draft') }}</option>
                    <option value="published">{{ __('admin.content.pages.published') }}</option>
                </select>
            </div>
        </div>
    </div>

    <div wire:loading.flex class="ag-loading" wire:target="search,publicationFilter,gotoPage,previousPage,nextPage">
        <span class="ag-loading__text">{{ __('admin.content.pages.loading') }}</span>
    </div>

    @if ($bulkResult !== null)
        <div class="ag-alert {{ $bulkResult['failed'] || $bulkResult['skipped'] ? 'ag-alert--danger' : 'ag-alert--success' }}" role="status" aria-live="polite" tabindex="-1">
            {{ __('admin.content.pages.bulk_result', $bulkResult) }}
        </div>
    @endif

    @if ($pages->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $search !== '' || $publicationFilter !== '' ? __('admin.content.pages.filtered_empty_title') : __('admin.content.pages.empty_title') }}</p>
            <p class="ag-empty__text">{{ $search !== '' || $publicationFilter !== '' ? __('admin.content.pages.filtered_empty_text') : __('admin.content.pages.empty_text') }}</p>
        </div>
    @else
    @can('pages.manage')
        <div class="ag-toolbar" role="region" aria-label="{{ __('admin.content.pages.bulk_selected', ['count' => count($selectedPageIds)]) }}">
            <button type="button" class="ag-btn" wire:click="selectCurrentPage">{{ __('admin.content.pages.bulk_select_page') }}</button>
            <span aria-live="polite">{{ __('admin.content.pages.bulk_selected', ['count' => count($selectedPageIds)]) }}</span>
            <button type="button" class="ag-btn" wire:click="bulkSetStatus('published')" wire:confirm="{{ __('admin.content.pages.bulk_confirm_publish') }}" @disabled(count($selectedPageIds) === 0)>{{ __('admin.content.pages.bulk_publish') }}</button>
            <button type="button" class="ag-btn" wire:click="bulkSetStatus('draft')" wire:confirm="{{ __('admin.content.pages.bulk_confirm_draft') }}" @disabled(count($selectedPageIds) === 0)>{{ __('admin.content.pages.bulk_draft') }}</button>
        </div>
        @error('selectedPageIds') <div class="ag-alert ag-alert--danger" role="alert">{{ $message }}</div> @enderror
        @error('bulkStatus') <div class="ag-alert ag-alert--danger" role="alert">{{ $message }}</div> @enderror
    @endcan
    <div class="ag-table-wrap" wire:loading.class="is-loading" wire:target="search,publicationFilter">
        <table class="ag-table">
                <thead>
                    <tr>
                        @can('pages.manage')<th scope="col"><span class="visually-hidden">{{ __('admin.content.pages.bulk_select_page') }}</span></th>@endcan
                        <th scope="col">{{ __('common.title') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr wire:key="page-{{ $page->id }}">
                            @can('pages.manage')
                                <td><x-ag.checkbox id="page-select-{{ $page->id }}" wire:model.live="selectedPageIds" value="{{ $page->id }}" aria-label="{{ __('admin.content.pages.bulk_select_row', ['title' => $page->title]) }}" /></td>
                            @endcan
                            <td>
                                <div class="ag-table__primary">
                                    @if ($page->status === 'published')
                                        <a class="ag-table__name" href="{{ route('storefront.page', ['slug' => $page->slug]) }}">{{ $page->title }}</a>
                                    @else
                                        <span class="ag-table__name">{{ $page->title }}</span>
                                    @endif
                                    <span class="ag-muted">/{{ $page->slug }}</span>
                                </div>
                            </td>
                            <td><span class="ag-badge">{{ __('admin.content.pages.'.$page->status) }}</span></td>
                            <td class="ag-table__actions">
                                <div class="ag-row-actions">
                                    @if ($page->status === 'published')
                                        <a class="ag-icon-btn" href="{{ route('storefront.page', ['slug' => $page->slug]) }}" title="{{ __('admin.content.pages.view') }}" aria-label="{{ __('admin.content.pages.view_aria', ['title' => $page->title]) }}">
                                            <x-ag.icon name="eye" :size="16" />
                                        </a>
                                    @endif
                                    @can('pages.manage')
                                        <a class="ag-icon-btn" href="{{ route('admin.appearance.pages.edit', $page) }}" title="{{ __('common.edit') }}" aria-label="{{ __('admin.content.pages.edit_aria', ['title' => $page->title]) }}">
                                            <x-ag.icon name="pencil" :size="16" />
                                        </a>
                                        <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="delete({{ $page->id }})" wire:confirm="{{ __('admin.content.pages.delete_confirm') }}" title="{{ __('common.delete') }}" aria-label="{{ __('admin.content.pages.delete_aria', ['title' => $page->title]) }}">
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
    <div class="ag-pagination">{{ $pages->links() }}</div>
    @endif
</div>
