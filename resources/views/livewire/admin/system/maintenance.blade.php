<div class="admin-page" id="storefront-maintenance">
    <x-ag.page-header
        :heading="__('admin.maintenance.title')"
        :lede="__('admin.maintenance.lede')"
    />

    @if (session('status'))
        <div class="ag-alert ag-alert--success" role="status" aria-live="polite">
            <div class="ag-alert__body">{{ session('status') }}</div>
        </div>
    @endif

    @if ($state->enabled)
        <div class="ag-alert ag-alert--warning" role="status">
            <div class="ag-alert__body">{{ __('admin.maintenance.active_notice') }}</div>
        </div>
    @endif

    <section class="ag-card" aria-labelledby="maintenance-status-heading">
        <header class="ag-card__header">
            <h2 id="maintenance-status-heading" class="ag-card__title">{{ __('admin.maintenance.status_title') }}</h2>
            <p class="ag-card__description">{{ __('admin.maintenance.status_description') }}</p>
        </header>
        <div class="ag-card__content">
            <dl class="ag-dl">
                <div>
                    <dt>{{ __('admin.maintenance.status_label') }}</dt>
                    <dd>
                        @if ($state->enabled)
                            <span class="ag-badge ag-badge--warning">{{ __('admin.maintenance.status_on') }}</span>
                        @else
                            <span class="ag-badge ag-badge--success">{{ __('admin.maintenance.status_off') }}</span>
                        @endif
                    </dd>
                </div>
                @if ($state->enabled && $enabledAt)
                    <div>
                        <dt>{{ __('admin.maintenance.enabled_at_label') }}</dt>
                        <dd>{{ $enabledAt->format('Y-m-d H:i') }}</dd>
                    </div>
                @endif
                @if ($state->enabled)
                    <div>
                        <dt>{{ __('admin.maintenance.ends_at_label') }}</dt>
                        <dd>{{ $endsAtDisplay ? $endsAtDisplay->format('Y-m-d H:i') : __('admin.maintenance.ends_at_none') }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </section>

    <section class="ag-card" aria-labelledby="maintenance-page-heading">
        <header class="ag-card__header">
            <h2 id="maintenance-page-heading" class="ag-card__title">{{ __('admin.maintenance.page_title') }}</h2>
            <p class="ag-card__description">{{ __('admin.maintenance.page_description') }}</p>
        </header>
        <div class="ag-card__content">
            <form class="ag-form" wire:submit="enable">
                <div class="ag-field">
                    <label class="ag-field__label" for="maintenance-message">{{ __('admin.maintenance.message_label') }}</label>
                    <textarea
                        id="maintenance-message"
                        class="ag-input ag-input--area"
                        rows="4"
                        maxlength="{{ $messageMaxLength }}"
                        wire:model="message"
                        aria-describedby="maintenance-message-help"
                    ></textarea>
                    <p id="maintenance-message-help" class="ag-field__help">{{ __('admin.maintenance.message_help', ['max' => $messageMaxLength]) }}</p>
                    @error('message')
                        <p class="ag-field__error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="ag-field">
                    <label class="ag-field__label" for="maintenance-ends-at">{{ __('admin.maintenance.ends_at_label') }}</label>
                    <input
                        id="maintenance-ends-at"
                        class="ag-input"
                        type="datetime-local"
                        wire:model="endsAt"
                        aria-describedby="maintenance-ends-at-help"
                    >
                    <p id="maintenance-ends-at-help" class="ag-field__help">{{ __('admin.maintenance.ends_at_help') }}</p>
                    @error('endsAt')
                        <p class="ag-field__error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div class="ag-form__actions">
                    @if ($state->enabled)
                        <button type="submit" class="ag-btn ag-btn--secondary" wire:loading.attr="disabled" wire:target="enable">
                            {{ __('admin.maintenance.save') }}
                        </button>
                        <button
                            type="button"
                            class="ag-btn ag-btn--primary"
                            wire:click="disable"
                            wire:loading.attr="disabled"
                            wire:target="disable"
                        >
                            {{ __('admin.maintenance.turn_off') }}
                        </button>
                    @else
                        <button
                            type="submit"
                            class="ag-btn ag-btn--danger"
                            wire:confirm="{{ __('admin.maintenance.turn_on_confirm') }}"
                            wire:loading.attr="disabled"
                            wire:target="enable"
                        >
                            {{ __('admin.maintenance.turn_on') }}
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </section>

    <section class="ag-card" aria-labelledby="maintenance-reachable-heading">
        <header class="ag-card__header">
            <h2 id="maintenance-reachable-heading" class="ag-card__title">{{ __('admin.maintenance.reachable_title') }}</h2>
            <p class="ag-card__description">{{ __('admin.maintenance.reachable_description') }}</p>
        </header>
        <div class="ag-card__content">
            <dl class="ag-dl">
                <div>
                    <dt>{{ __('admin.maintenance.closed_label') }}</dt>
                    <dd>{{ __('admin.maintenance.closed_text') }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.maintenance.open_label') }}</dt>
                    <dd>{{ __('admin.maintenance.open_text') }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.maintenance.recovery_label') }}</dt>
                    <dd>
                        {{ __('admin.maintenance.recovery_text') }}
                        <code>php artisan agovena:maintenance off</code>
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    @include('livewire.admin.partials.confirm-password-modal')
</div>
