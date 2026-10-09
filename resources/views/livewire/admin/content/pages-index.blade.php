<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__heading">{{ __('admin.content.pages.title') }}</h1>
            <p class="admin-page__lede">{{ __('admin.content.pages.lede') }}</p>
        </div>
        @can('pages.manage')
            <a class="ag-btn ag-btn--primary" href="{{ route('admin.appearance.pages.create') }}">{{ __('admin.content.pages.new') }}</a>
        @endcan
    </header>

    <div class="ag-table-wrap">
        <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('common.title') }}</th>
                        <th>{{ __('common.status') }}</th>
                        @can('pages.manage')
                            <th scope="col">{{ __('common.actions') }}</th>
                        @endcan
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
                            @can('pages.manage')
                                <td>
                                    <a class="ag-btn ag-btn--ghost" href="{{ route('admin.appearance.pages.edit', $page) }}">{{ __('common.edit') }}</a>
                                    <button type="button" class="ag-btn ag-btn--ghost" wire:click="delete({{ $page->id }})" wire:confirm="{{ __('admin.content.pages.delete_confirm') }}">{{ __('common.delete') }}</button>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('pages.manage') ? 3 : 2 }}">
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
