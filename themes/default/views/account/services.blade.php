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
                            <strong>
                                <a href="{{ route('customer.services.show', $instance) }}">
                                    {{ $instance->product?->name ?? $instance->number }}
                                </a>
                            </strong>
                            <p>{{ __('provisioning::customer.status') }}: {{ __('provisioning::status.'.$instance->status->value) }}</p>
                            @if ($instance->external_ref)
                                <p>{{ __('provisioning::customer.reference') }}: {{ $instance->external_ref }}</p>
                            @endif
                        </div>
                        <a class="store-btn store-btn--secondary" href="{{ route('customer.services.show', $instance) }}">
                            {{ __('provisioning::customer.manage') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
