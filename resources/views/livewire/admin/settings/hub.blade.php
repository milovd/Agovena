<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">Settings</h2>
            <p class="admin-page__lede">Configure your store. Groups are registered by Core and extensions through Agovena contracts.</p>
        </div>
    </header>

    @if ($groups->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">No settings available</p>
            <p class="ag-empty__text">You do not have permission to view any settings groups.</p>
        </div>
    @else
        <div class="ag-settings-hub" role="list">
            @foreach ($groups as $group)
                <a
                    class="ag-settings-card"
                    role="listitem"
                    href="{{ $group->resolveHref() }}"
                    wire:key="settings-group-{{ $group->id }}"
                >
                    <span class="ag-settings-card__icon" aria-hidden="true">
                        <x-ag.icon :name="$group->icon ?? 'settings'" :size="22" />
                    </span>
                    <span class="ag-settings-card__body">
                        <span class="ag-settings-card__title">{{ $group->label }}</span>
                        @if ($group->description)
                            <span class="ag-settings-card__text">{{ $group->description }}</span>
                        @endif
                    </span>
                    <span class="ag-settings-card__chevron" aria-hidden="true">
                        <x-ag.icon name="chevron-right" :size="18" />
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
