<h1 id="install-step-heading" class="install-panel__title">{{ __('installer.branding.heading') }}</h1>
<p class="install-panel__lede">{{ __('installer.branding.lede') }}</p>

<form wire:submit="next" class="install-form" novalidate>
    <div class="ag-field">
        <label class="ag-field__label" for="logo">{{ __('installer.fields.logo') }}</label>
        <input id="logo" class="ag-input" type="file" wire:model="logo" accept="image/*">
        <div wire:loading wire:target="logo" class="ag-field__help">{{ __('installer.branding.uploading') }}</div>
        @error('logo') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="ag-field">
        <x-ag.checkbox
            id="useLogoAsFavicon"
            wire:model="useLogoAsFavicon"
            :label="__('installer.fields.use_logo_as_favicon')"
        />
    </div>

    @if (! $useLogoAsFavicon)
        <div class="ag-field">
            <label class="ag-field__label" for="favicon">{{ __('installer.fields.favicon') }}</label>
            <input id="favicon" class="ag-input" type="file" wire:model="favicon" accept="image/*">
            <div wire:loading wire:target="favicon" class="ag-field__help">{{ __('installer.branding.uploading') }}</div>
            @error('favicon') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
    @endif

    <div class="install-panel__actions">
        <button type="button" class="ag-btn ag-btn--ghost" wire:click="back">{{ __('installer.actions.back') }}</button>
        <button type="button" class="ag-btn ag-btn--secondary" wire:click="skipBranding">{{ __('installer.actions.skip') }}</button>
        <button type="submit" class="ag-btn ag-btn--primary">{{ __('installer.actions.continue') }}</button>
    </div>
</form>
