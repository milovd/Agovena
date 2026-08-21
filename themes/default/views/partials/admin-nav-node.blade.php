@php
    use App\Agovena\Admin\AdminNavigation;
    use App\Agovena\Admin\AdminNavigationNode;

    /** @var AdminNavigationNode $node */
    $item = $node->item;
    $active = AdminNavigation::isActive($item->href);
    $branchActive = $node->childIsActive();
    $openByDefault = $node->hasChildren();
@endphp

<li
    @class([
        'admin-nav__item',
        'admin-nav__item--branch' => $node->hasChildren(),
    ])
    @if ($node->hasChildren())
        x-data="{
            open: {{ $openByDefault ? 'true' : 'false' }},
            init() {
                const key = 'agovena.admin.nav.item.v5.{{ $item->id }}';
                const stored = localStorage.getItem(key);
                if ({{ $node->isActive() ? 'true' : 'false' }}) {
                    this.open = true;
                } else if (stored === '0') {
                    this.open = false;
                }
                this.$watch('open', (value) => localStorage.setItem(key, value ? '1' : '0'));
            }
        }"
        :class="{ 'admin-nav__item--open': open }"
    @endif
>
    <div class="admin-nav__row">
        <a
            @class([
                'admin-nav__link',
                'admin-nav__link--active' => $active,
                'admin-nav__link--branch-active' => $branchActive && ! $active,
            ])
            href="{{ $item->href }}"
            @if ($active) aria-current="page" @endif
        >
            @if ($item->icon)
                <x-ag.icon :name="$item->icon" class="admin-nav__icon" :size="18" />
            @endif
            <span class="admin-nav__label">{{ __($item->label) }}</span>
        </a>
        @if ($node->hasChildren())
            <button
                type="button"
                class="admin-nav__toggle"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="nav-branch-{{ $item->id }}"
            >
                <span class="visually-hidden">{{ __('admin.nav_toggle_children') }}</span>
                <x-ag.icon name="chevron-down" class="admin-nav__branch" :size="14" />
            </button>
        @endif
    </div>
    @if ($node->hasChildren())
        <ul
            id="nav-branch-{{ $item->id }}"
            class="admin-nav__sub"
            role="list"
            x-show="open"
        >
            @foreach ($node->children as $child)
                @php $childActive = AdminNavigation::isActive($child->href); @endphp
                <li>
                    <a
                        @class([
                            'admin-nav__link',
                            'admin-nav__link--child',
                            'admin-nav__link--active' => $childActive,
                        ])
                        href="{{ $child->href }}"
                        @if ($childActive) aria-current="page" @endif
                    >
                        @if ($child->icon)
                            <x-ag.icon :name="$child->icon" class="admin-nav__icon" :size="16" />
                        @endif
                        <span class="admin-nav__label">{{ __($child->label) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</li>
