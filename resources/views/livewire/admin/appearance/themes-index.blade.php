<div class="admin-page">
    <x-ag.page-header :heading="__('admin.appearance.themes.title')" :lede="__('admin.appearance.themes.lede')">
        <x-slot:actions>
            @can('theme.manage')
                <a class="ag-btn ag-btn--secondary" href="{{ route('admin.appearance.customize') }}">{{ __('admin.appearance.themes.customize_active') }}</a>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if (count($themes) > 0)
        <section class="c-theme-grid" aria-label="{{ __('admin.appearance.themes.title') }}">
            @foreach ($themes as $theme)
                <article class="c-theme-card" wire:key="theme-{{ $theme->id }}">
                    <div class="c-theme-card__preview" aria-hidden="true">
                        @if (app(\App\Agovena\Packages\PackageArtwork::class)->resolve($theme->basePath, $theme->previewReference, themePreview: true))
                            <img src="{{ route('admin.appearance.themes.preview', ['id' => $theme->id]) }}" alt="" loading="lazy" decoding="async">
                        @else
                            <x-ag.icon name="layout-template" :size="40" />
                        @endif
                    </div>
                    <div class="c-theme-card__body">
                        <div class="c-theme-card__top">
                            <div class="c-theme-card__identity">
                                <h2 class="c-theme-card__title">{{ $theme->name }}</h2>
                                <p class="c-theme-card__version">{{ __('admin.appearance.themes.version') }} {{ $theme->version }}</p>
                            </div>
                            @if ($theme->id === $activeId)
                                <span class="ag-badge ag-badge--success">{{ __('admin.appearance.themes.active') }}</span>
                            @else
                                <span class="ag-badge">{{ __('admin.appearance.themes.installed') }}</span>
                            @endif
                        </div>
                        <p class="c-theme-card__description">{{ $theme->description }}</p>
                        @can('theme.manage')
                            @if ($theme->id !== $activeId)
                                <div class="c-theme-card__actions">
                                    <button type="button" class="ag-btn ag-btn--primary" wire:click="activate('{{ $theme->id }}')" wire:confirm="{{ __('admin.appearance.themes.confirm_activate', ['name' => $theme->name]) }}">{{ __('admin.appearance.themes.activate') }}</button>
                                </div>
                            @endif
                        @endcan
                    </div>
                </article>
            @endforeach
        </section>
    @else
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.appearance.themes.empty_title') }}</p>
            <p class="ag-empty__text">
                {!! __('admin.appearance.themes.empty_text', [
                    'path' => '<code>themes/{id}</code>',
                    'file' => '<code>theme.json</code>',
                ]) !!}
            </p>
        </div>
    @endif
</div>
