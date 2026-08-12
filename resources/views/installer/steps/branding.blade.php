<h1 id="install-step-heading" class="install-panel__title">{{ __('installer.branding.heading') }}</h1>
<p class="install-panel__lede">{{ __('installer.branding.lede') }}</p>

<p class="install-note">{{ __('installer.branding.product_vs_store') }}</p>

<form wire:submit="next" class="install-form" novalidate>
    <x-ag.file-upload
        id="logo"
        wire:model="logo"
        :label="__('installer.fields.logo')"
        :hint="__('installer.branding.logo_hint')"
        accept="image/jpeg,image/png,image/webp,image/gif"
        loading-target="logo"
        :preview-url="$logo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile ? $logo->temporaryUrl() : null"
        :preview-alt="__('installer.fields.logo')"
        :error="$errors->first('logo')"
    />

    <div class="ag-field">
        <x-ag.checkbox
            id="useLogoAsFavicon"
            wire:model.live="useLogoAsFavicon"
            :label="__('installer.fields.use_logo_as_favicon')"
        />
    </div>

    @if (! $useLogoAsFavicon)
        <x-ag.file-upload
            id="favicon"
            wire:model="favicon"
            :label="__('installer.fields.favicon')"
            :hint="__('installer.branding.favicon_hint')"
            accept="image/jpeg,image/png,image/webp,image/gif,image/x-icon"
            loading-target="favicon"
            :preview-url="$favicon instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile ? $favicon->temporaryUrl() : null"
            :preview-alt="__('installer.fields.favicon')"
            :error="$errors->first('favicon')"
        />
    @endif

    <div class="install-panel__actions">
        <button type="button" class="ag-btn ag-btn--ghost" wire:click="back">{{ __('installer.actions.back') }}</button>
        <button type="button" class="ag-btn ag-btn--secondary" wire:click="skipBranding">{{ __('installer.actions.skip') }}</button>
        <button type="submit" class="ag-btn ag-btn--primary">{{ __('installer.actions.continue') }}</button>
    </div>
</form>
