<div class="admin-page">
    <x-ag.page-header :heading="__('admin.security.title')" :lede="__('admin.security.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if ($forced)
        <div class="ag-alert ag-alert--warning" role="status">
            <div class="ag-alert__body">
                <p class="ag-alert__title">{{ __('admin.security.required_title') }}</p>
                <p class="ag-alert__text">{{ __('admin.security.required_text') }}</p>
            </div>
        </div>
    @endif

    @if ($enabled)
        <section class="admin-panel">
            <h3 class="admin-panel__title">{{ __('admin.security.enabled_heading') }}</h3>
            <p class="ag-muted">{{ __('admin.security.enabled_text') }}</p>
            <div class="ag-form__actions">
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="regenerateRecoveryCodes">
                    {{ __('admin.security.regenerate_recovery') }}
                </button>
                <button type="button" class="ag-btn ag-btn--danger" wire:click="disable">
                    {{ __('admin.security.disable') }}
                </button>
            </div>
        </section>
    @elseif ($setupSecret !== '')
        <section class="admin-panel">
            <h3 class="admin-panel__title">{{ __('admin.security.setup_heading') }}</h3>
            <p class="ag-muted">{{ __('admin.security.setup_text') }}</p>
            @if ($qrSvg)
                <div class="ag-qr" aria-hidden="true">{!! $qrSvg !!}</div>
            @endif
            <p class="ag-field__help">{{ __('admin.security.manual_secret') }}</p>
            <p><code>{{ $setupSecret }}</code></p>
            <form wire:submit="confirmSetup" class="ag-form">
                <div class="ag-field">
                    <label class="ag-field__label" for="totp-code">{{ __('admin.security.code') }}</label>
                    <input
                        id="totp-code"
                        class="ag-input"
                        type="text"
                        inputmode="numeric"
                        wire:model="code"
                        autocomplete="one-time-code"
                        required
                    >
                    @error('code') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-form__actions">
                    <button type="submit" class="ag-btn ag-btn--primary">{{ __('admin.security.confirm') }}</button>
                </div>
            </form>
        </section>
    @else
        <section class="admin-panel">
            <h3 class="admin-panel__title">{{ __('admin.security.enable_heading') }}</h3>
            <p class="ag-muted">{{ __('admin.security.enable_text') }}</p>
            <div class="ag-form__actions">
                <button type="button" class="ag-btn ag-btn--primary" wire:click="startSetup">
                    {{ __('admin.security.start') }}
                </button>
            </div>
        </section>
    @endif

    @if ($showingRecoveryCodes)
        <section class="admin-panel" role="status">
            <h3 class="admin-panel__title">{{ __('admin.security.recovery_heading') }}</h3>
            <p class="ag-muted">{{ __('admin.security.recovery_text') }}</p>
            <ul>
                @foreach ($recoveryCodes as $recoveryCode)
                    <li><code>{{ $recoveryCode }}</code></li>
                @endforeach
            </ul>
            <button type="button" class="ag-btn ag-btn--secondary" wire:click="hideRecoveryCodes">
                {{ __('admin.security.recovery_done') }}
            </button>
        </section>
    @endif

    @include('livewire.admin.partials.confirm-password-modal')
</div>
