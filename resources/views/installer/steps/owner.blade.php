<h1 id="install-step-heading" class="install-panel__title">{{ __('installer.owner.heading') }}</h1>
<p class="install-panel__lede">{{ __('installer.owner.lede') }}</p>

<form wire:submit="next" class="install-form" novalidate>
    <div class="ag-field">
        <label class="ag-field__label" for="ownerName">{{ __('installer.fields.owner_name') }}</label>
        <input
            id="ownerName"
            class="ag-input"
            type="text"
            wire:model="ownerName"
            autocomplete="name"
            required
            aria-invalid="{{ $errors->has('ownerName') ? 'true' : 'false' }}"
            @if ($errors->has('ownerName')) aria-describedby="ownerName-error" @endif
        >
        @error('ownerName') <p id="ownerName-error" class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="ag-field">
        <label class="ag-field__label" for="ownerEmail">{{ __('installer.fields.owner_email') }}</label>
        <input
            id="ownerEmail"
            class="ag-input"
            type="email"
            wire:model="ownerEmail"
            autocomplete="username"
            required
            aria-invalid="{{ $errors->has('ownerEmail') ? 'true' : 'false' }}"
            @if ($errors->has('ownerEmail')) aria-describedby="ownerEmail-error" @endif
        >
        @error('ownerEmail') <p id="ownerEmail-error" class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="ag-field">
        <label class="ag-field__label" for="ownerPassword">{{ __('installer.fields.owner_password') }}</label>
        <input
            id="ownerPassword"
            class="ag-input"
            type="password"
            wire:model="ownerPassword"
            autocomplete="new-password"
            required
            aria-invalid="{{ $errors->has('ownerPassword') ? 'true' : 'false' }}"
            @if ($errors->has('ownerPassword')) aria-describedby="ownerPassword-error" @endif
        >
        @error('ownerPassword') <p id="ownerPassword-error" class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="ag-field">
        <label class="ag-field__label" for="ownerPasswordConfirmation">{{ __('installer.fields.owner_password_confirmation') }}</label>
        <input
            id="ownerPasswordConfirmation"
            class="ag-input"
            type="password"
            wire:model="ownerPasswordConfirmation"
            autocomplete="new-password"
            required
            aria-invalid="{{ $errors->has('ownerPasswordConfirmation') ? 'true' : 'false' }}"
            @if ($errors->has('ownerPasswordConfirmation')) aria-describedby="ownerPasswordConfirmation-error" @endif
        >
        @error('ownerPasswordConfirmation') <p id="ownerPasswordConfirmation-error" class="ag-field__error" role="alert">{{ $message }}</p> @enderror
    </div>

    <div class="install-panel__actions">
        <button type="button" class="ag-btn ag-btn--ghost" wire:click="back">{{ __('installer.actions.back') }}</button>
        <button type="submit" class="ag-btn ag-btn--primary">{{ __('installer.actions.continue') }}</button>
    </div>
</form>
