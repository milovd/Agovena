<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('provisioning::customer.title')],
            ],
        ])

        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="server" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">{{ __('provisioning::customer.title') }}</h1>
                    <p class="store-support-hero__lede">{{ __('provisioning::customer.lede') }}</p>
                </div>
            </div>
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
