@php
    use App\Agovena\Admin\AdminNavigation;
    use App\Agovena\Admin\AdminNavigationNode;
    use App\Agovena\Modules\ModuleManager;

    $staff = auth()->user();
    $nav = AdminNavigation::filterVisible(collect($navigation ?? []), app(ModuleManager::class))
        ->filter(function ($item) use ($staff) {
        $authorized = $item->permission === null
            || ($staff !== null && $staff->can($item->permission));

        return $authorized && is_string($item->href) && $item->href !== '';
    });
    $groups = AdminNavigation::groupedTree($nav);
@endphp

@foreach ($groups as $group => $nodes)
    @php
        $groupSlug = \Illuminate\Support\Str::slug($group);
        $groupHasActive = $nodes->contains(fn (AdminNavigationNode $node) => $node->isActive());
    @endphp
    <div
        class="admin-nav__section"
        x-data="agAdminNavGroup"
        data-nav-key="agovena.admin.nav.v6.{{ $groupSlug }}"
        data-open="{{ in_array($group, ['admin.nav_groups.overview', 'admin.nav_groups.catalog'], true) ? 'true' : 'false' }}"
        data-active="{{ $groupHasActive ? 'true' : 'false' }}"
        :class="{ 'admin-nav__section--collapsed': !open }"
    >
        <button
            type="button"
            class="admin-nav__group"
            id="nav-group-{{ $groupSlug }}"
            @click="toggle()"
            :aria-expanded="open.toString()"
            aria-controls="nav-group-panel-{{ $groupSlug }}"
        >
            <span class="admin-nav__group-label">{{ __($group) }}</span>
            <x-ag.icon name="chevron-down" class="admin-nav__group-chevron" :size="14" />
        </button>
        <ul
            id="nav-group-panel-{{ $groupSlug }}"
            class="admin-nav__list"
            role="list"
            aria-labelledby="nav-group-{{ $groupSlug }}"
            x-show="open"
        >
            @foreach ($nodes as $node)
                @include('partials.admin.nav-node', ['node' => $node])
            @endforeach
        </ul>
    </div>
@endforeach
