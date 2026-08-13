<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('provisioning::customer.title') }}</h1>
            <p class="store-account-panel__lede">{{ __('provisioning::customer.lede') }}</p>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @if ($instances->isEmpty())
            <p class="store-muted">{{ __('provisioning::customer.empty') }}</p>
        @else
            <ul class="store-order-items" role="list">
                @foreach ($instances as $instance)
                    <li class="store-order-items__row" wire:key="customer-svc-{{ $instance->id }}">
                        <div>
                            <strong>{{ $instance->product?->name ?? $instance->number }}</strong>
                            <p>{{ __('provisioning::customer.status') }}: {{ __('provisioning::status.'.$instance->status->value) }}</p>
                            @if ($instance->product)
                                <p>{{ __('provisioning::customer.plan') }}: {{ $instance->product->name }}</p>
                            @endif
                            @if (! empty($instance->meta['options_snapshot']))
                                <ul>
                                    @foreach ($instance->meta['options_snapshot'] as $option)
                                        <li>{{ $option['label'] ?? $option['key'] ?? '' }}: {{ $option['display'] ?? $option['value'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($instance->subscription_id)
                                <p>{{ __('provisioning::customer.linked_subscription') }}: {{ $instance->subscription_id }}</p>
                            @endif
                            @if ($instance->external_ref)
                                <p>{{ __('provisioning::customer.reference') }}: {{ $instance->external_ref }}</p>
                            @endif
                            @if ($providerData[$instance->id]['panel'])
                                <section aria-labelledby="provider-panel-{{ $instance->id }}">
                                    <h2 id="provider-panel-{{ $instance->id }}">{{ $providerData[$instance->id]['panel']->title }}</h2>
                                    <dl>
                                        @foreach ($providerData[$instance->id]['panel']->fields as $field)
                                            <div>
                                                <dt>{{ $field['label'] }}</dt>
                                                <dd>{{ $field['value'] }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </section>
                            @endif
                        </div>
                        @if ($providerData[$instance->id]['actions'] !== [])
                            <div>
                                @foreach ($providerData[$instance->id]['actions'] as $action)
                                    <button
                                        type="button"
                                        class="store-btn {{ $action->dangerous ? 'store-btn--danger' : 'store-btn--secondary' }}"
                                        wire:click="runAction({{ $instance->id }}, @js($action->id))"
                                    >
                                        {{ $action->label }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
