<h1 id="install-step-heading" class="install-panel__title">{{ __('installer.catalog.heading') }}</h1>
<p class="install-panel__lede">{{ __('installer.catalog.lede') }}</p>
<p class="ag-field__help">{{ __('installer.catalog.not_locked') }}</p>

<form wire:submit="next" class="install-form" novalidate>
    <fieldset class="install-themes">
        <legend class="visually-hidden">{{ __('installer.catalog.choose') }}</legend>
        @foreach ($catalog as $preset)
            <label class="install-theme @if (in_array($preset->id, $presetIds, true)) is-selected @endif" wire:key="install-preset-{{ $preset->id }}">
                <input
                    class="install-theme__input"
                    type="checkbox"
                    value="{{ $preset->id }}"
                    wire:model.live="presetIds"
                >
                <span class="install-theme__body">
                    <span class="install-theme__name">{{ __($preset->labelKey) }}</span>
                    <span class="install-theme__desc">{{ __($preset->ledeKey) }}</span>
                </span>
            </label>
        @endforeach
    </fieldset>

    <div class="install-panel__actions">
        <button type="button" class="ag-btn ag-btn--ghost" wire:click="back">{{ __('installer.actions.back') }}</button>
        <button type="button" class="ag-btn ag-btn--ghost" wire:click="skipCatalog">{{ __('installer.actions.skip') }}</button>
        <button type="submit" class="ag-btn ag-btn--primary">{{ __('installer.actions.continue') }}</button>
    </div>
</form>
