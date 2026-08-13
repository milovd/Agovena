<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <p class="store-account-panel__back">
                <a href="{{ route('customer.services') }}">{{ __('provisioning::customer.back') }}</a>
            </p>
            <h1 class="store-account-panel__title">{{ $instance->product?->name ?? $instance->number }}</h1>
            <p class="store-account-panel__lede">{{ $instance->number }}</p>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        <dl class="store-account-panel__grid">
            <div>
                <dt>{{ __('provisioning::customer.status') }}</dt>
                <dd>{{ __('provisioning::status.'.$instance->status->value) }}</dd>
            </div>
            @if ($instance->product)
                <div>
                    <dt>{{ __('provisioning::customer.plan') }}</dt>
                    <dd>{{ $instance->product->name }}</dd>
                </div>
            @endif
            @if ($instance->external_ref)
                <div>
                    <dt>{{ __('provisioning::customer.reference') }}</dt>
                    <dd>{{ $instance->external_ref }}</dd>
                </div>
            @endif
            @if ($instance->order)
                <div>
                    <dt>{{ __('provisioning::customer.order') }}</dt>
                    <dd><a href="{{ route('customer.orders.show', $instance->order) }}">{{ $instance->order->number }}</a></dd>
                </div>
            @endif
            @if ($instance->order?->invoice)
                <div>
                    <dt>{{ __('customer.account.invoice_number') }}</dt>
                    <dd><a href="{{ route('customer.invoices.show', $instance->order->invoice) }}">{{ $instance->order->invoice->number }}</a></dd>
                </div>
            @endif
            @if ($subscription)
                <div>
                    <dt>{{ __('provisioning::customer.linked_subscription') }}</dt>
                    <dd>
                        @if (\Illuminate\Support\Facades\Route::has('customer.subscriptions.show'))
                            <a href="{{ route('customer.subscriptions.show', $subscription) }}">{{ $subscription->number }}</a>
                        @else
                            {{ $subscription->number }}
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        @if (! empty($instance->meta['options_snapshot']))
            <section class="store-account-panel__section">
                <h2>{{ __('provisioning::customer.options') }}</h2>
                <ul>
                    @foreach ($instance->meta['options_snapshot'] as $option)
                        <li>{{ $option['label'] ?? $option['key'] ?? '' }}: {{ $option['display'] ?? $option['value'] ?? '' }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($panel)
            <section class="store-account-panel__section" aria-labelledby="provider-panel">
                <h2 id="provider-panel">{{ $panel->title }}</h2>
                <dl>
                    @foreach ($panel->fields as $field)
                        <div>
                            <dt>{{ $field['label'] }}</dt>
                            <dd>
                                @if (is_string($field['value']) && preg_match('#^https?://#', $field['value']) === 1)
                                    <a href="{{ $field['value'] }}" rel="noopener noreferrer" target="_blank">{{ $field['value'] }}</a>
                                @else
                                    {{ $field['value'] }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($actions !== [])
            <section class="store-account-panel__section">
                <h2>{{ __('provisioning::customer.actions') }}</h2>
                @foreach ($actions as $action)
                    <button
                        type="button"
                        class="store-btn {{ $action->dangerous ? 'store-btn--danger' : 'store-btn--secondary' }}"
                        wire:click="runAction(@js($action->id))"
                        @if ($action->dangerous) wire:confirm="{{ __('provisioning::customer.action_confirm') }}" @endif
                    >
                        {{ $action->label }}
                    </button>
                @endforeach
            </section>
        @endif
    </section>
</div>
