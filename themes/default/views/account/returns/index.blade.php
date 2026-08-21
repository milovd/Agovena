<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('shipping::returns.customer_title')],
            ],
        ])

        <header class="store-account-panel__header">
            <div>
                <h1 class="store-account-panel__title">{{ __('shipping::returns.customer_title') }}</h1>
                <p class="store-account-panel__lede">{{ __('shipping::returns.customer_lede') }}</p>
            </div>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        <section class="store-account-dashboard__recent" aria-labelledby="eligible-returns-heading">
            <div class="store-account-dashboard__recent-head">
                <div>
                    <h2 id="eligible-returns-heading" class="store-account-dashboard__section-title">{{ __('shipping::returns.customer_eligible_title') }}</h2>
                </div>
            </div>

            @if ($eligibleOrders->isEmpty())
                <x-ag.empty :title="__('shipping::returns.customer_eligible_empty')">
                    <x-slot:icon>
                        <x-ag.icon name="package" :size="22" />
                    </x-slot:icon>
                </x-ag.empty>
            @else
                <div class="store-account-card-list" role="list">
                    @foreach ($eligibleOrders as $order)
                        <article class="store-account-entry" role="listitem" wire:key="eligible-order-{{ $order->id }}">
                            <div class="store-account-entry__body">
                                <p class="store-account-entry__title">{{ $order->number }}</p>
                                <p class="store-account-entry__meta">
                                    {{ $order->created_at?->timezone(config('app.timezone'))->locale(app()->getLocale())->translatedFormat('j F Y') }}
                                </p>
                            </div>
                            <a class="store-btn store-btn--secondary" href="{{ route('customer.returns.create', $order) }}">
                                {{ __('shipping::returns.customer_start') }}
                            </a>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="store-account-dashboard__recent" aria-labelledby="return-requests-heading">
            <div class="store-account-dashboard__recent-head">
                <div>
                    <h2 id="return-requests-heading" class="store-account-dashboard__section-title">{{ __('shipping::returns.customer_requests_title') }}</h2>
                </div>
            </div>

            @if ($requests->isEmpty())
                <p class="store-account-panel__empty">{{ __('shipping::returns.customer_empty') }}</p>
            @else
                <div class="store-account-card-list" role="list">
                    @foreach ($requests as $request)
                        @php
                            $statusClass = match ($request->status->value) {
                                'approved', 'received', 'completed' => 'is-success',
                                'rejected', 'cancelled' => 'is-muted',
                                default => 'is-warning',
                            };
                        @endphp
                        <article class="store-account-entry" role="listitem" wire:key="return-{{ $request->id }}">
                            <div class="store-account-entry__body">
                                <p class="store-account-entry__title">#{{ $request->id }} · {{ $request->order?->number }}</p>
                                <p class="store-order-card__status {{ $statusClass }}">
                                    {{ __('shipping::returns.statuses.'.$request->status->value) }}
                                </p>
                                @if ($request->reason)
                                    <p class="store-account-entry__meta">{{ $request->reason }}</p>
                                @endif
                                <ul class="store-account-entry__lines">
                                    @foreach ($request->items as $item)
                                        <li>{{ $item->quantity }} × {{ $item->orderItem?->name ?? $item->orderItem?->label ?? ('#'.$item->order_item_id) }}</li>
                                    @endforeach
                                </ul>
                                @if ($request->staff_notes)
                                    <p class="store-account-entry__meta">
                                        {{ __('shipping::returns.customer_staff_notes') }}: {{ $request->staff_notes }}
                                    </p>
                                @endif
                            </div>
                            @if ($request->status->value === 'requested')
                                <button
                                    type="button"
                                    class="store-btn store-btn--secondary"
                                    wire:click="cancel({{ $request->id }})"
                                >
                                    {{ __('shipping::returns.customer_cancel') }}
                                </button>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <p class="store-account-dashboard__hint">{{ __('shipping::returns.customer_refund_note') }}</p>
    </section>
</div>
