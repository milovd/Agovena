<h1 id="install-step-heading" class="install-panel__title">{{ __('installer.regional.heading') }}</h1>
<p class="install-panel__lede">{{ __('installer.regional.lede') }}</p>

<form wire:submit="next" class="install-form" novalidate>
    <div class="ag-field">
        <label class="ag-field__label" for="locale">{{ __('installer.fields.locale') }}</label>
        <select id="locale" class="ag-select" wire:model="locale" required>
            @foreach ($locales as $code => $label)
                <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
        </select>
        <p class="ag-field__help">{{ __('installer.regional.locale_help') }}</p>
        @error('locale') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="ag-field">
        <label class="ag-field__label" for="timezone">{{ __('installer.fields.timezone') }}</label>
        <input
            id="timezone"
            class="ag-input"
            type="text"
            list="timezone-options"
            wire:model="timezone"
            autocomplete="off"
            required
        >
        <datalist id="timezone-options">
            @foreach ($timezones as $tz)
                <option value="{{ $tz }}"></option>
            @endforeach
        </datalist>
        @error('timezone') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="ag-field">
        <label class="ag-field__label" for="currency">{{ __('installer.fields.currency') }}</label>
        <select id="currency" class="ag-select" wire:model="currency" required>
            @foreach ($currencies as $row)
                <option value="{{ $row->code }}">{{ $row->code }} — {{ $row->name }}</option>
            @endforeach
        </select>
        @error('currency') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="install-panel__actions">
        <button type="button" class="ag-btn ag-btn--ghost" wire:click="back">{{ __('installer.actions.back') }}</button>
        <button type="submit" class="ag-btn ag-btn--primary">{{ __('installer.actions.continue') }}</button>
    </div>
</form>
