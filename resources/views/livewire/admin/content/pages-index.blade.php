<div class="admin-page admin-page--pages">
    <x-ag.page-header :heading="__('admin.content.pages.title')" :lede="__('admin.content.pages.lede')">
        <x-slot:actions>
            @can('pages.manage')
                <a class="ag-btn ag-btn--primary" href="{{ route('admin.appearance.pages.create') }}"><x-ag.icon name="plus" :size="16" />{{ __('admin.content.pages.new') }}</a>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    <div class="ag-toolbar ag-toolbar--filters ag-page-list__filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline ag-page-list__search">
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
        @php
            $visibleIds = $pages->getCollection()->pluck('id')->map(static fn ($id) => (string) $id)->all();
            $selectedIds = array_map(static fn ($id) => is_int($id) || is_string($id) ? (string) $id : '', $selectedPageIds);
            $allSelected = $visibleIds !== [] && array_diff($visibleIds, $selectedIds) === [];
        @endphp
    @can('pages.manage')
        <div class="ag-toolbar ag-page-list__bulk-mobile" role="region" aria-label="{{ __('admin.content.pages.bulk_selected', ['count' => count($selectedPageIds)]) }}">
            <button type="button" class="ag-bulk-selection__select" role="checkbox" aria-checked="{{ $allSelected ? 'true' : 'false' }}" wire:click="selectCurrentPage" title="{{ __($allSelected ? 'admin.content.pages.bulk_deselect_page' : 'admin.content.pages.bulk_select_page') }}" aria-label="{{ __($allSelected ? 'admin.content.pages.bulk_deselect_page' : 'admin.content.pages.bulk_select_page') }}">@if($allSelected)<x-ag.icon name="check" :size="13" />@endif</button>
            <span aria-live="polite">{{ __('admin.content.pages.bulk_selected', ['count' => count($selectedPageIds)]) }}</span>
            <div class="ag-menu ag-bulk-selection__actions" x-data="agDisclosure" @keydown.escape.window="close(true)" @click.outside="close()">
                <button type="button" class="ag-icon-btn" @click="toggle()" :aria-expanded="open.toString()" aria-controls="page-bulk-actions-mobile" title="{{ __('common.actions') }}" aria-label="{{ __('common.actions') }}" @disabled(count($selectedPageIds) === 0)><x-ag.icon name="more-horizontal" :size="16" /></button>
                <div id="page-bulk-actions-mobile" class="ag-menu__panel" x-show="open" x-cloak role="group" aria-label="{{ __('common.actions') }}">
                    <button type="button" class="ag-menu__item" wire:click="bulkSetStatus('published')" wire:confirm="{{ __('admin.content.pages.bulk_confirm_publish') }}" @disabled(count($selectedPageIds) === 0)>{{ __('admin.content.pages.bulk_publish') }}</button>
                    <button type="button" class="ag-menu__item" wire:click="bulkSetStatus('draft')" wire:confirm="{{ __('admin.content.pages.bulk_confirm_draft') }}" @disabled(count($selectedPageIds) === 0)>{{ __('admin.content.pages.bulk_draft') }}</button>
                </div>
            </div>
        </div>
        @error('selectedPageIds') <div class="ag-alert ag-alert--danger" role="alert">{{ $message }}</div> @enderror
        @error('bulkStatus') <div class="ag-alert ag-alert--danger" role="alert">{{ $message }}</div> @enderror
    @endcan
    <div class="ag-table-wrap ag-page-list__wrap" wire:loading.class="is-loading" wire:target="search,publicationFilter">
        <table class="ag-table ag-page-list__table">
                <thead>
                    <tr>
                        @can('pages.manage')<th scope="col"><button type="button" class="ag-bulk-selection__select" role="checkbox" aria-checked="{{ $allSelected ? 'true' : 'false' }}" wire:click="selectCurrentPage" title="{{ __($allSelected ? 'admin.content.pages.bulk_deselect_page' : 'admin.content.pages.bulk_select_page') }}" aria-label="{{ __($allSelected ? 'admin.content.pages.bulk_deselect_page' : 'admin.content.pages.bulk_select_page') }}">@if($allSelected)<x-ag.icon name="check" :size="13" />@endif</button></th>@endcan
                        <th scope="col">{{ __('common.title') }} @can('pages.manage')<span class="ag-bulk-selection__count" aria-live="polite">{{ __('admin.content.pages.bulk_selected', ['count' => count($selectedPageIds)]) }}</span>@endcan</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col" class="ag-bulk-selection__header-actions">
                            @can('pages.manage')
                                <div class="ag-menu ag-bulk-selection__actions" x-data="agDisclosure" @keydown.escape.window="close(true)" @click.outside="close()">
                                    <button type="button" class="ag-icon-btn" @click="toggle()" :aria-expanded="open.toString()" aria-controls="page-bulk-actions" title="{{ __('common.actions') }}" aria-label="{{ __('common.actions') }}" @disabled(count($selectedPageIds) === 0)><x-ag.icon name="more-horizontal" :size="16" /></button>
                                    <div id="page-bulk-actions" class="ag-menu__panel" x-show="open" x-cloak role="group" aria-label="{{ __('common.actions') }}">
                                        <button type="button" class="ag-menu__item" wire:click="bulkSetStatus('published')" wire:confirm="{{ __('admin.content.pages.bulk_confirm_publish') }}" @disabled(count($selectedPageIds) === 0)>{{ __('admin.content.pages.bulk_publish') }}</button>
                                        <button type="button" class="ag-menu__item" wire:click="bulkSetStatus('draft')" wire:confirm="{{ __('admin.content.pages.bulk_confirm_draft') }}" @disabled(count($selectedPageIds) === 0)>{{ __('admin.content.pages.bulk_draft') }}</button>
                                    </div>
                                </div>
                            @else
                                <span class="visually-hidden">{{ __('common.actions') }}</span>
                            @endcan
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr wire:key="page-{{ $page->id }}" @can('pages.manage')class="ag-page-list__row--selectable"@endcan>
                            @can('pages.manage')
                                <td class="ag-page-list__selection"><x-ag.checkbox id="page-select-{{ $page->id }}" wire:model.live="selectedPageIds" value="{{ $page->id }}" aria-label="{{ __('admin.content.pages.bulk_select_row', ['title' => $page->title]) }}" /></td>
                            @endcan
                            <td class="ag-page-list__identity">
                                <div class="ag-page-list__identity-inner">
                                    <span class="ag-icon-tile" data-tone="blue" aria-hidden="true"><x-ag.icon name="file-text" :size="17" /></span>
                                    <div class="ag-table__primary">
                                        @if ($page->status === 'published')
                                            <a class="ag-table__name" href="{{ route('storefront.page', ['slug' => $page->slug]) }}">{{ $page->title }}</a>
                                        @else
                                            <span class="ag-table__name">{{ $page->title }}</span>
                                        @endif
                                        <span class="ag-muted">/{{ $page->slug }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="ag-page-list__status" data-label="{{ __('common.status') }}"><span class="ag-badge {{ $page->status === 'published' ? 'ag-badge--success' : 'ag-badge--muted' }}">{{ __('admin.content.pages.'.$page->status) }}</span></td>
                            <td class="ag-table__actions ag-page-list__actions">
                                <div class="ag-row-actions">
                                    @can('pages.manage')
                                        <a class="ag-icon-btn" href="{{ route('admin.appearance.pages.edit', $page) }}" title="{{ __('common.edit') }}" aria-label="{{ __('admin.content.pages.edit_aria', ['title' => $page->title]) }}">
                                            <x-ag.icon name="pencil" :size="16" />
                                        </a>
                                        <div class="ag-menu" x-data="agDisclosure" @keydown.escape.window="close(true)" @click.outside="close()">
                                            <button type="button" class="ag-icon-btn" @click="toggle()" :aria-expanded="open.toString()" aria-controls="page-actions-{{ $page->id }}" title="{{ __('common.actions') }}" aria-label="{{ __('common.actions') }}: {{ $page->title }}">
                                                <x-ag.icon name="more-horizontal" :size="16" />
                                            </button>
                                            <div id="page-actions-{{ $page->id }}" class="ag-menu__panel" x-show="open" x-cloak role="group" aria-label="{{ __('common.actions') }}">
                                                @if ($page->status === 'published')
                                                    <a class="ag-menu__item" href="{{ route('storefront.page', ['slug' => $page->slug]) }}">{{ __('admin.content.pages.view') }}</a>
                                                @endif
                                                <button type="button" class="ag-menu__item ag-menu__item--danger" wire:click="delete({{ $page->id }})" wire:confirm="{{ __('admin.content.pages.delete_confirm') }}">{{ __('common.delete') }}</button>
                                            </div>
                                        </div>
                                    @else
                                        @if ($page->status === 'published')
                                            <a class="ag-icon-btn" href="{{ route('storefront.page', ['slug' => $page->slug]) }}" title="{{ __('admin.content.pages.view') }}" aria-label="{{ __('admin.content.pages.view_aria', ['title' => $page->title]) }}">
                                                <x-ag.icon name="eye" :size="16" />
                                            </a>
                                        @endif
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
