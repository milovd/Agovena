<div class="admin-page">
    <x-ag.page-header
        :heading="__('admin.settings.title')"
        :lede="$groupDefinition?->description ? __($groupDefinition->description) : __('admin.settings.lede')"
    />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if ($groups->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.settings.empty_title') }}</p>
            <p class="ag-empty__text">{{ __('admin.settings.empty_text') }}</p>
        </div>
    @else
        @include('livewire.admin.partials.package-tabs', [
            'active' => $tab,
            'tabs' => $tabs,
            'ariaLabel' => __('admin.settings.tabs_aria'),
        ])

        @if ($externalGroups->isNotEmpty())
            <div class="ag-toolbar" style="margin-bottom: 1rem;">
                @foreach ($externalGroups as $external)
                    <a class="ag-btn ag-btn--secondary ag-btn--sm" href="{{ $external->resolveHref() }}" wire:key="settings-external-{{ $external->id }}">
                        {{ __($external->label) }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($groupDefinition !== null && $groupDefinition->href === null)
            @include('livewire.admin.settings.partials.group-form')
        @endif
    @endif
</div>
