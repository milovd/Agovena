<section class="admin-panel" style="margin-bottom: 1.5rem;">
    <h2 class="admin-panel__title">{{ __('admin.packages.install_title') }}</h2>
    <p class="ag-muted">{{ __('admin.packages.install_lede') }}</p>
    <p class="ag-alert ag-alert--warning" role="note">{{ __('admin.packages.trust_warning') }}</p>
    <form wire:submit="installRemote" class="ag-stack">
        <div class="ag-field">
            <label class="ag-field__label" for="package-name-{{ $kind }}">{{ __('admin.packages.composer_name') }}</label>
            <input id="package-name-{{ $kind }}" class="ag-input" type="text" wire:model="packageName" autocomplete="off" placeholder="vendor/package">
        </div>
        <div class="ag-field">
            <label class="ag-field__label" for="package-constraint-{{ $kind }}">{{ __('admin.packages.constraint') }}</label>
            <input id="package-constraint-{{ $kind }}" class="ag-input" type="text" wire:model="versionConstraint" autocomplete="off">
        </div>
        <div class="ag-field">
            <label class="ag-field__label" for="package-repo-{{ $kind }}">{{ __('admin.packages.repository_url') }}</label>
            <input id="package-repo-{{ $kind }}" class="ag-input" type="url" wire:model="repositoryUrl" autocomplete="off" placeholder="https://github.com/vendor/package">
            <p class="ag-muted">{{ __('admin.packages.repository_help') }}</p>
        </div>
        <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.packages.actions.install') }}</button>
    </form>
</section>
