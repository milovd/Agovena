<section class="admin-panel" style="margin-bottom: 1.5rem;">
    <h2 class="admin-panel__title">{{ __('admin.packages.zip_title') }}</h2>
    <p class="ag-muted">{{ __('admin.packages.zip_lede') }}</p>
    <form wire:submit="installFromZip" class="ag-stack">
        <div class="ag-field">
            <label class="ag-field__label" for="package-zip-{{ $kind }}">{{ __('admin.packages.zip_file') }}</label>
            <input id="package-zip-{{ $kind }}" class="ag-input" type="file" accept=".zip,application/zip" wire:model="packageZip">
            @error('packageZip')
                <p class="ag-field__error">{{ $message }}</p>
            @enderror
            <p class="ag-muted">{{ __('admin.packages.zip_help') }}</p>
        </div>
        <div class="ag-form__actions">
            <button class="ag-btn ag-btn--primary" type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="installFromZip,packageZip">{{ __('admin.packages.actions.upload_install') }}</span>
                <span wire:loading wire:target="installFromZip,packageZip">{{ __('common.loading') }}</span>
            </button>
        </div>
    </form>
</section>
