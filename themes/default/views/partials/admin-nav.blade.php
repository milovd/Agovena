@php
    use App\Agovena\Admin\AdminNavigation;
    use App\Agovena\Admin\AdminNavigationNode;

    $staff = auth()->user();
    $nav = collect($navigation ?? [])->filter(function ($item) use ($staff) {
        $authorized = $item->permission === null
            || ($staff !== null && $staff->can($item->permission));

        return $authorized && is_string($item->href) && $item->href !== '';
    });
    $groups = AdminNavigation::groupedTree($nav);
@endphp

@foreach ($groups as $group => $nodes)
    @php
        $groupSlug = \Illuminate\Support\Str::slug($group);
        $isOverview = $group === 'admin.nav_groups.overview';
        $groupHasActive = $nodes->contains(fn (AdminNavigationNode $node) => $node->isActive());
    @endphp
    <div
        class="admin-nav__section"
        @if (! $isOverview)
            x-data="{
                open: true,
                init() {
                    const key = 'agovena.admin.nav.v6.{{ $groupSlug }}';
                    const stored = localStorage.getItem(key);
                    if ({{ $groupHasActive ? 'true' : 'false' }}) {
                        this.open = true;
                    } else if (stored === '0') {
                        this.open = false;
                    }
                    this.$watch('open', (value) => localStorage.setItem(key, value ? '1' : '0'));
                }
            }"
            :class="{ 'admin-nav__section--collapsed': !open }"
        @endif
    >
        @if ($isOverview)
            <p class="admin-nav__group admin-nav__group--static" id="nav-group-{{ $groupSlug }}">{{ __($group) }}</p>
        @else
            <button
                type="button"
                class="admin-nav__group"
                id="nav-group-{{ $groupSlug }}"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="nav-group-panel-{{ $groupSlug }}"
            >
                <span class="admin-nav__group-label">{{ __($group) }}</span>
                <x-ag.icon name="chevron-down" class="admin-nav__group-chevron" :size="14" />
            </button>
        @endif
        <ul
            id="nav-group-panel-{{ $groupSlug }}"
            class="admin-nav__list"
            role="list"
            aria-labelledby="nav-group-{{ $groupSlug }}"
            @if (! $isOverview) x-show="open" @endif
        >
            @foreach ($nodes as $node)
                @include('partials.admin-nav-node', ['node' => $node])
            @endforeach
        </ul>
    </div>
@endforeach
