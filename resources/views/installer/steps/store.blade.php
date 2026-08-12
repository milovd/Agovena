<h1 id="install-step-heading" class="install-panel__title">{{ __('installer.store.heading') }}</h1>
<p class="install-panel__lede">{{ __('installer.store.lede') }}</p>

<form wire:submit="next" class="install-form" novalidate>
    <div class="ag-field">
        <label class="ag-field__label" for="siteName">{{ __('installer.fields.site_name') }}</label>
        <input
            id="siteName"
            class="ag-input"
            type="text"
            wire:model="siteName"
            autocomplete="organization"
            required
            aria-invalid="{{ $errors->has('siteName') ? 'true' : 'false' }}"
            @if ($errors->has('siteName')) aria-describedby="siteName-error" @endif
        >
        @error('siteName') <p id="siteName-error" class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="ag-field">
        <span class="ag-field__label">{{ __('installer.fields.store_url') }}</span>
        <p class="install-readonly" aria-readonly="true">{{ $appUrl }}</p>
        <p class="ag-field__help">{{ __('installer.store.url_help') }}</p>
    </div>

    <div class="install-panel__actions">
        <button type="button" class="ag-btn ag-btn--ghost" wire:click="back">{{ __('installer.actions.back') }}</button>
        <button type="submit" class="ag-btn ag-btn--primary">{{ __('installer.actions.continue') }}</button>
    </div>
</form>
