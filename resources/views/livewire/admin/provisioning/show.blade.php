<div class="admin-page">
    <x-ag.page-header
        :heading="__('provisioning::admin.show_title', ['number' => $instance->number])"
        :lede="$instance->product?->name"
    />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <section class="ag-section">
        <header class="ag-section__header">
            <h3 class="ag-section__title">{{ __('provisioning::admin.details') }}</h3>
        </header>
        <div class="ag-section__body ag-grid ag-grid--2">
            <p><strong>{{ __('provisioning::admin.status') }}:</strong> {{ __('provisioning::status.'.$instance->status->value) }}</p>
            <p><strong>{{ __('provisioning::admin.customer') }}:</strong> {{ $instance->customer_email }}</p>
            @if ($instance->order)
                <p><strong>{{ __('provisioning::admin.order') }}:</strong>
                    <a href="{{ route('admin.orders.show', $instance->order) }}">{{ $instance->order->number }}</a>
                </p>
            @endif
            @if ($instance->failure_message)
                <p><strong>{{ __('provisioning::admin.failure') }}:</strong> {{ $instance->failure_message }}</p>
            @endif
        </div>
    </section>

    @can('provisioning.manage')
        <section class="ag-section">
            <header class="ag-section__header">
                <h3 class="ag-section__title">{{ __('provisioning::admin.tracking') }}</h3>
            </header>
            <form wire:submit="saveTracking" class="ag-section__body ag-grid ag-grid--2">
                <div class="ag-field">
                    <label class="ag-field__label" for="provider_key">{{ __('provisioning::admin.provider_key') }}</label>
                    <input id="provider_key" class="ag-input" type="text" wire:model="provider_key">
                    <p class="ag-field__hint">{{ __('provisioning::admin.provider_key_hint') }}</p>
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="external_ref">{{ __('provisioning::admin.external_ref') }}</label>
                    <input id="external_ref" class="ag-input" type="text" wire:model="external_ref">
                </div>
                <div>
                    <button type="submit" class="ag-btn ag-btn--secondary">{{ __('provisioning::admin.save_tracking') }}</button>
                </div>
            </form>
        </section>

        <section class="ag-section">
            <header class="ag-section__header">
                <h3 class="ag-section__title">{{ __('provisioning::admin.actions') }}</h3>
            </header>
            <div class="ag-section__body" style="display:flex; gap:.75rem; flex-wrap:wrap;">
                @if (in_array($instance->status->value, ['pending', 'failed'], true))
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="markProvisioning">
                        {{ __('provisioning::admin.mark_provisioning') }}
                    </button>
                @endif
                @if ($instance->canActivate())
                    <button type="button" class="ag-btn ag-btn--primary" wire:click="activate">
                        {{ __('provisioning::admin.activate') }}
                    </button>
                @endif
                @if ($instance->canSuspend())
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="suspend">
                        {{ __('provisioning::admin.suspend') }}
                    </button>
                @endif
                @if ($instance->canTerminate())
                    <button type="button" class="ag-btn ag-btn--danger" wire:click="terminate">
                        {{ __('provisioning::admin.terminate') }}
                    </button>
                @endif
            </div>
        </section>
    @endcan

    <p><a href="{{ route('admin.provisioning.index') }}">{{ __('provisioning::admin.back') }}</a></p>
</div>
