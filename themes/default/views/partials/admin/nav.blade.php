@php
    use App\Agovena\Admin\AdminNavigation;
    use App\Agovena\Modules\ModuleManager;

    $nav = AdminNavigation::filterForStaff(
        AdminNavigation::filterVisible(collect($navigation ?? []), app(ModuleManager::class)),
        auth()->user(),
    );
    $groups = AdminNavigation::groupedTree($nav);
@endphp

<ul class="admin-nav__list" role="list">
    @foreach ($groups as $group => $nodes)
        @php $links = AdminNavigation::groupLinks($nodes); @endphp
        @if (! AdminNavigation::groupHasLabel($group))
            @foreach ($links as $link)
                @php $label = __($link->label); @endphp
                <li class="admin-nav__item">
                    <a
                        @class(['admin-nav__link', 'admin-nav__link--active' => AdminNavigation::isActive($link->href, $nav)])
                        href="{{ $link->href }}"
                        data-tooltip="{{ $label }}"
                        @if (AdminNavigation::isCurrentPage($link->href)) aria-current="page" @endif
                    >
                        <x-ag.icon :name="$link->icon ?? 'blocks'" class="admin-nav__icon" :size="16" />
                        <span class="admin-nav__label">{{ $label }}</span>
                    </a>
                </li>
            @endforeach
        @else
            @php
                $groupSlug = \Illuminate\Support\Str::slug($group);
                $groupLabel = __($group);
                $groupActive = collect($links)->contains(fn ($link) => AdminNavigation::isActive($link->href, $nav));
            @endphp
            <li
                class="admin-nav__item admin-nav__item--group"
                data-nav-group="{{ $groupSlug }}"
                x-data="agAdminNavGroup"
                data-nav-key="agovena.admin.nav.group.v8.{{ $groupSlug }}"
                data-open="false"
                data-active="{{ $groupActive ? 'true' : 'false' }}"
                :class="{ 'admin-nav__item--open': open }"
            >
                <button
                    type="button"
                    @class(['admin-nav__link', 'admin-nav__trigger', 'admin-nav__trigger--active' => $groupActive])
                    id="nav-group-{{ $groupSlug }}"
                    @click="toggle()"
                    :aria-expanded="open.toString()"
                    aria-controls="nav-group-panel-{{ $groupSlug }}"
                    data-tooltip="{{ $groupLabel }}"
                >
                    <x-ag.icon :name="AdminNavigation::groupIcon($group, $nodes)" class="admin-nav__icon" :size="16" />
                    <span class="admin-nav__label">{{ $groupLabel }}</span>
                    <x-ag.icon name="chevron-right" class="admin-nav__chevron" :size="16" />
                </button>
                <ul
                    id="nav-group-panel-{{ $groupSlug }}"
                    class="admin-nav__sub"
                    role="list"
                    aria-labelledby="nav-group-{{ $groupSlug }}"
                    x-show="open"
                    @if (! $groupActive) x-cloak @endif
                >
                    @foreach ($links as $link)
                        <li>
                            <a
                                @class(['admin-nav__sub-link', 'admin-nav__sub-link--active' => AdminNavigation::isActive($link->href, $nav)])
                                href="{{ $link->href }}"
                                @if (AdminNavigation::isCurrentPage($link->href)) aria-current="page" @endif
                            >{{ __($link->label) }}</a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endif
    @endforeach
</ul>
