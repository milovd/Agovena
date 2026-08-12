@php
    use Illuminate\Support\Facades\Lang;

    $menuName = static function ($menu): string {
        $key = 'admin.content.navigation.menu_names.'.$menu->handle;

        return Lang::has($key) ? __($key) : $menu->name;
    };
@endphp

<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">{{ __('admin.content.navigation.title') }}</h2>
            <p class="admin-page__lede">{{ __('admin.content.navigation.lede') }}</p>
        </div>
    </header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-toolbar" role="tablist" aria-label="{{ __('admin.content.navigation.menus_aria') }}">
        @foreach ($menus as $m)
            <button
                type="button"
                class="ag-btn {{ $m->handle === $selectedHandle ? 'ag-btn--primary' : '' }}"
                wire:click="selectMenu('{{ $m->handle }}')"
            >{{ $menuName($m) }}</button>
        @endforeach
    </div>

    <div class="ag-split">
        <div class="admin-panel">
            <h3 class="admin-panel__title">{{ __('admin.content.navigation.add_item_to', ['menu' => $menuName($menu)]) }}</h3>
            <form class="ag-form" wire:submit="addItem">
                <div class="ag-field">
                    <label class="ag-field__label" for="nav-label">{{ __('admin.content.navigation.label') }}</label>
                    <input id="nav-label" class="ag-input" type="text" wire:model="label" required>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="nav-type">{{ __('admin.content.navigation.type') }}</label>
                    <select id="nav-type" class="ag-select" wire:model.live="type">
                        <option value="url">{{ __('admin.content.navigation.types.url') }}</option>
                        <option value="page">{{ __('admin.content.navigation.types.page') }}</option>
                        <option value="category">{{ __('admin.content.navigation.types.category') }}</option>
                    </select>
                </div>
                @if ($type === 'url')
                    <div class="ag-field">
                        <label class="ag-field__label" for="nav-url">{{ __('admin.content.navigation.url') }}</label>
                        <input id="nav-url" class="ag-input" type="text" wire:model="url" placeholder="{{ __('admin.content.navigation.url_placeholder') }}">
                    </div>
                @elseif ($type === 'page')
                    <div class="ag-field">
                        <label class="ag-field__label" for="nav-page">{{ __('admin.content.navigation.types.page') }}</label>
                        <select id="nav-page" class="ag-select" wire:model="page_id">
                            <option value="">{{ __('common.select_placeholder') }}</option>
                            @foreach ($pages as $page)
                                <option value="{{ $page->id }}">{{ $page->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="ag-field">
                        <label class="ag-field__label" for="nav-cat">{{ __('admin.content.navigation.types.category') }}</label>
                        <select id="nav-cat" class="ag-select" wire:model="category_id">
                            <option value="">{{ __('common.select_placeholder') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="ag-btn ag-btn--primary">{{ __('admin.content.navigation.add_item') }}</button>
            </form>
        </div>

        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th>{{ __('admin.content.navigation.label') }}</th>
                        <th>{{ __('admin.content.navigation.type') }}</th>
                        <th>{{ __('admin.content.navigation.target') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menu->allItems as $item)
                        <tr wire:key="menu-item-{{ $item->id }}">
                            <td>{{ $item->label }}</td>
                            <td>{{ __('admin.content.navigation.types.'.$item->type) }}</td>
                            <td class="ag-muted">
                                @if ($item->type === 'url')
                                    {{ $item->url }}
                                @elseif ($item->type === 'page')
                                    {{ $item->page?->title ?? __('common.em_dash') }}
                                @else
                                    {{ $item->category?->name ?? __('common.em_dash') }}
                                @endif
                            </td>
                            <td>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="deleteItem({{ $item->id }})" wire:confirm="{{ __('admin.content.navigation.remove_confirm') }}">{{ __('common.remove') }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="ag-empty" role="status">
                                    <p class="ag-empty__title">{{ __('admin.content.navigation.empty_title') }}</p>
                                    <p class="ag-empty__text">{{ __('admin.content.navigation.empty_text') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
