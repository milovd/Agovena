@if ($showingPasswordConfirmation)
    <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-password-title">
        <div class="ag-modal__backdrop" wire:click="cancelPasswordConfirmation"></div>
        <div class="ag-modal__panel">
            <h3 id="confirm-password-title" class="ag-modal__title">{{ __('admin.security.confirm_password_title') }}</h3>
            <p class="ag-modal__text">{{ __('admin.security.confirm_password_text') }}</p>
            <form wire:submit="confirmRecentPassword">
                <div class="ag-field">
                    <label class="ag-field__label" for="recent-password">{{ __('admin.security.password') }}</label>
                    <input
                        id="recent-password"
                        class="ag-input"
                        type="password"
                        wire:model="recentPassword"
                        autocomplete="current-password"
                        required
                    >
                    @error('recentPassword') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-modal__actions">
                    <button type="submit" class="ag-btn ag-btn--primary">{{ __('common.confirm') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelPasswordConfirmation">{{ __('common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
@endif
