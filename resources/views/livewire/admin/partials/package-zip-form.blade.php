<section class="admin-panel">
    <div class="admin-panel__header">
        <div>
            <h2 class="admin-panel__title">{{ __('admin.packages.zip_title') }}</h2>
            <p class="ag-muted">{{ __('admin.packages.zip_lede') }}</p>
        </div>
    </div>

    <form wire:submit="installFromZip" class="ag-stack">
        <x-ag.file-upload
            id="package-zip-{{ $kind }}"
            :label="__('admin.packages.zip_file')"
            :hint="__('admin.packages.zip_help')"
            accept=".zip,application/zip"
            placeholder-icon="package"
            :button-label="__('admin.packages.actions.choose_zip')"
            wire:model="packageZip"
            loading-target="installFromZip,packageZip"
        >
            @error('packageZip')
                <p class="ag-field__error" role="alert">{{ $message }}</p>
            @enderror
        </x-ag.file-upload>

        <div class="ag-form__actions">
            <button class="ag-btn ag-btn--primary" type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="installFromZip,packageZip">{{ __('admin.packages.actions.upload_install') }}</span>
                <span wire:loading wire:target="installFromZip,packageZip">{{ __('common.loading') }}</span>
            </button>
        </div>
    </form>
</section>
