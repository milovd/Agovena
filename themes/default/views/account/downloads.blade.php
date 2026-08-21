<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('digital::customer.title')],
            ],
        ])

        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="download" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">{{ __('digital::customer.title') }}</h1>
                    <p class="store-support-hero__lede">{{ __('digital::customer.lede') }}</p>
                </div>
            </div>
        </header>

        @if ($entitlements->isEmpty())
            <p class="store-muted">{{ __('digital::customer.empty') }}</p>
        @else
            <ul class="store-order-items" role="list">
                @foreach ($entitlements as $entitlement)
                    <li class="store-order-items__row" wire:key="entitlement-{{ $entitlement->id }}">
                        <div>
                            <strong>{{ $entitlement->asset?->label ?? __('digital::customer.file') }}</strong>
                            <p>
                                {{ __('digital::customer.order') }}:
                                {{ $entitlement->order?->number }}
                            </p>
                            @if ($entitlement->download_limit !== null)
                                <p>{{ __('digital::customer.remaining', ['count' => $entitlement->remainingDownloads()]) }}</p>
                            @endif
                        </div>
                        @if ($entitlement->canDownload())
                            <a class="store-btn store-btn--secondary" href="{{ route('customer.downloads.file', $entitlement->token) }}">
                                {{ __('digital::customer.download') }}
                            </a>
                        @else
                            <span class="store-muted">{{ __('digital::customer.exhausted') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
