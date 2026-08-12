<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">{{ __('admin.settings.title') }}</h2>
            <p class="admin-page__lede">{{ __('admin.settings.lede') }}</p>
        </div>
    </header>

    @if ($groups->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.settings.empty_title') }}</p>
            <p class="ag-empty__text">{{ __('admin.settings.empty_text') }}</p>
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
                        <span class="ag-settings-card__title">{{ __($group->label) }}</span>
                        @if ($group->description)
                            <span class="ag-settings-card__text">{{ __($group->description) }}</span>
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
