<div class="install-complete">
    <p class="install-complete__eyebrow">{{ __('installer.complete.eyebrow') }}</p>
    <h1 id="install-step-heading" class="install-panel__title">{{ __('installer.complete.heading') }}</h1>
    <p class="install-panel__lede">{{ __('installer.complete.lede', ['store' => $siteName]) }}</p>

    <div class="install-panel__actions">
        <a class="ag-btn ag-btn--primary" href="{{ route('admin.login') }}">{{ __('installer.actions.open_admin') }}</a>
        <a class="ag-btn ag-btn--secondary" href="{{ route('storefront.home') }}">{{ __('installer.actions.view_storefront') }}</a>
    </div>
</div>
