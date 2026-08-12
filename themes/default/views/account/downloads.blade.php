<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('digital::customer.title') }}</h1>
            <p class="store-account-panel__lede">{{ __('digital::customer.lede') }}</p>
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
