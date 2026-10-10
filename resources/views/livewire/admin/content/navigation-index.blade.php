@php
    use Illuminate\Support\Facades\Lang;

    $menuName = static function ($menu): string {
        $key = 'admin.content.navigation.menu_names.'.$menu->handle;

        return Lang::has($key) ? __($key) : $menu->name;
    };
@endphp

<div class="admin-page c-navigation">
    <header class="admin-page__header">
        <div>
            <h1 class="admin-page__heading">{{ __('admin.content.navigation.title') }}</h1>
            <p class="admin-page__lede">{{ __('admin.content.navigation.lede') }}</p>
        </div>
    </header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <nav class="c-navigation__tabs" aria-label="{{ __('admin.content.navigation.menus_aria') }}">
        @foreach ($menus as $m)
            <button
                type="button"
                class="ag-btn c-navigation__tab {{ $m->handle === $selectedHandle ? 'ag-btn--primary' : '' }}"
                wire:click="selectMenu('{{ $m->handle }}')"
                @if ($m->handle === $selectedHandle) aria-current="true" @endif
            >{{ $menuName($m) }}</button>
        @endforeach
    </nav>

    <div class="c-navigation__layout">
        @can('navigation.manage')
        <section class="admin-panel c-navigation__form-panel" aria-labelledby="nav-form-heading">
            <div class="c-navigation__panel-heading">
                <x-ag.icon name="plus" :size="24" aria-hidden="true" />
                <h2 id="nav-form-heading" class="admin-panel__title">{{ __('admin.content.navigation.add_item_to', ['menu' => $menuName($menu)]) }}</h2>
            </div>
            <form class="ag-form" wire:submit="addItem">
                <div class="ag-field">
                    <label class="ag-field__label" for="nav-label">{{ __('admin.content.navigation.label') }}</label>
                    <input id="nav-label" class="ag-input" type="text" wire:model="label" required>
                    @error('label') <p class="ag-field__error">{{ $message }}</p> @enderror
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
                        @error('url') <p class="ag-field__error">{{ $message }}</p> @enderror
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
                        @error('page_id') <p class="ag-field__error">{{ $message }}</p> @enderror
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
                        @error('category_id') <p class="ag-field__error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <button type="submit" class="ag-btn ag-btn--primary"><x-ag.icon name="plus" :size="18" aria-hidden="true" />{{ __('admin.content.navigation.add_item') }}</button>
            </form>
        </section>
        @endcan

        <section class="admin-panel c-navigation__structure-panel" aria-labelledby="nav-structure-heading">
            <div class="c-navigation__panel-heading">
                <x-ag.icon name="menu" :size="24" aria-hidden="true" />
                <h2 id="nav-structure-heading" class="admin-panel__title">{{ __('admin.content.navigation.menus_aria') }}: {{ $menuName($menu) }}</h2>
            </div>
            <div class="ag-table-wrap c-navigation__table-wrap">
            <table class="ag-table c-navigation__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.content.navigation.label') }}</th>
                        <th scope="col">{{ __('admin.content.navigation.type') }}</th>
                        <th scope="col">{{ __('admin.content.navigation.target') }}</th>
                        @can('navigation.manage') <th scope="col"><span class="ag-visually-hidden">{{ __('common.remove') }}</span></th> @endcan
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menu->allItems as $item)
                        <tr wire:key="menu-item-{{ $item->id }}">
                            <td class="c-navigation__label" data-label="{{ __('admin.content.navigation.label') }}">{{ $item->label }}</td>
                            <td data-label="{{ __('admin.content.navigation.type') }}">{{ __('admin.content.navigation.types.'.$item->type) }}</td>
                            <td class="c-navigation__target ag-muted" data-label="{{ __('admin.content.navigation.target') }}">
                                @if ($item->type === 'url')
                                    {{ $item->url }}
                                @elseif ($item->type === 'page')
                                    {{ $item->page?->title ?? __('common.em_dash') }}
                                @else
                                    {{ $item->category?->name ?? __('common.em_dash') }}
                                @endif
                            </td>
                            @can('navigation.manage')
                                <td class="c-navigation__actions">
                                    <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="deleteItem({{ $item->id }})" wire:confirm="{{ __('admin.content.navigation.remove_confirm') }}"><x-ag.icon name="trash" :size="16" aria-hidden="true" />{{ __('common.remove') }}</button>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr class="c-navigation__empty-row">
                            <td colspan="{{ auth()->user()->can('navigation.manage') ? 4 : 3 }}">
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
        </section>
    </div>
</div>
