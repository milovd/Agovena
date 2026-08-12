@php
    $localeLabel = $locales[$locale] ?? $locale;
    $themeLabel = collect($themes)->firstWhere('id', $themeId)?->name ?? $themeId;
@endphp

<div class="install-complete">
    <x-ag.logo variant="hero" class="install-complete__logo" :alt="__('installer.brand_alt')" />
    <p class="install-complete__eyebrow">{{ __('installer.complete.eyebrow') }}</p>
    <h1 id="install-step-heading" class="install-panel__title">{{ __('installer.complete.heading') }}</h1>
    <p class="install-panel__lede">{{ __('installer.complete.lede', ['store' => $siteName]) }}</p>

    <dl class="install-summary">
        <div>
            <dt>{{ __('installer.complete.summary_store') }}</dt>
            <dd>{{ $siteName }}</dd>
        </div>
        <div>
            <dt>{{ __('installer.complete.summary_locale') }}</dt>
            <dd>{{ $localeLabel }}</dd>
        </div>
        <div>
            <dt>{{ __('installer.complete.summary_currency') }}</dt>
            <dd>{{ $currency }}</dd>
        </div>
        <div>
            <dt>{{ __('installer.complete.summary_theme') }}</dt>
            <dd>{{ $themeLabel }}</dd>
        </div>
    </dl>

    <div class="install-panel__actions">
        <a class="ag-btn ag-btn--primary" href="{{ route('admin.dashboard') }}">{{ __('installer.actions.open_admin') }}</a>
        <a class="ag-btn ag-btn--secondary" href="{{ route('storefront.home') }}">{{ __('installer.actions.view_storefront') }}</a>
    </div>
</div>
