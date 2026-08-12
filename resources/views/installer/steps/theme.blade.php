<h1 id="install-step-heading" class="install-panel__title">{{ __('installer.theme.heading') }}</h1>
<p class="install-panel__lede">{{ __('installer.theme.lede') }}</p>

<form wire:submit="install" class="install-form" novalidate>
    <fieldset class="install-themes">
        <legend class="visually-hidden">{{ __('installer.fields.theme') }}</legend>
        @foreach ($themes as $theme)
            <label class="install-theme @if ($themeId === $theme->id) is-selected @endif">
                <input
                    type="radio"
                    name="themeId"
                    value="{{ $theme->id }}"
                    wire:model="themeId"
                    class="install-theme__input"
                >
                <span class="install-theme__body">
                    <span class="install-theme__name">{{ $theme->name }}</span>
                    <span class="install-theme__meta">v{{ $theme->version }}</span>
                    @if ($theme->description !== '')
                        <span class="install-theme__desc">{{ $theme->description }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </fieldset>
    <p class="ag-field__help">{{ __('installer.theme.customize_later') }}</p>
    @error('themeId') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror

    @if ($installError !== '')
        <p class="ag-field__error" role="alert">{{ $installError }}</p>
    @endif

    <div class="install-panel__actions">
        <button type="button" class="ag-btn ag-btn--ghost" wire:click="back">{{ __('installer.actions.back') }}</button>
        <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="install">{{ __('installer.actions.install') }}</span>
            <span wire:loading wire:target="install">{{ __('installer.actions.installing') }}</span>
        </button>
    </div>
</form>
