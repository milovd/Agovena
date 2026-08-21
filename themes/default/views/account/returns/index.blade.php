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
                <div class="store-return-list" role="list">
                    @foreach ($eligibleOrders as $order)
                        @php
                            $previewItems = $order->items->take(3);
                            $extraCount = max(0, $order->items->count() - $previewItems->count());
                        @endphp
                        <article class="store-return-card" role="listitem" wire:key="eligible-order-{{ $order->id }}">
                            <div class="store-return-card__thumbs" aria-hidden="true">
                                @foreach ($previewItems as $item)
                                    @php
                                        $imageUrl = \App\Agovena\Media\ProductMedia::primaryUrl($item->product);
                                    @endphp
                                    <div class="store-return-card__thumb">
                                        @if ($imageUrl)
                                            <img src="{{ $imageUrl }}" alt="">
                                        @else
                                            <span class="store-return-card__thumb-placeholder"></span>
                                        @endif
                                    </div>
                                @endforeach
                                @if ($extraCount > 0)
                                    <div class="store-return-card__thumb store-return-card__thumb--more">+{{ $extraCount }}</div>
                                @endif
                            </div>
                            <div class="store-return-card__body">
                                <p class="store-return-card__title">{{ $order->number }}</p>
                                <p class="store-return-card__meta">
                                    {{ $order->created_at?->timezone(config('app.timezone'))->locale(app()->getLocale())->translatedFormat('j F Y') }}
                                    ·
                                    {{ trans_choice('shipping::returns.customer_item_count', $order->items->count(), ['count' => $order->items->count()]) }}
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
                <div class="store-return-list" role="list">
                    @foreach ($requests as $request)
                        @php
                            $statusClass = match ($request->status->value) {
                                'approved', 'received', 'completed' => 'is-success',
                                'rejected', 'cancelled' => 'is-muted',
                                default => 'is-warning',
                            };
                        @endphp
                        <article class="store-return-card store-return-card--stack" role="listitem" wire:key="return-{{ $request->id }}">
                            <div class="store-return-card__header">
                                <div>
                                    <p class="store-return-card__title">#{{ $request->id }} · {{ $request->order?->number }}</p>
                                    <p class="store-order-card__status {{ $statusClass }}">
                                        {{ __('shipping::returns.statuses.'.$request->status->value) }}
                                    </p>
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
                            </div>

                            <ul class="store-return-card__items" role="list">
                                @foreach ($request->items as $item)
                                    @php
                                        $orderItem = $item->orderItem;
                                        $imageUrl = \App\Agovena\Media\ProductMedia::primaryUrl($orderItem?->product);
                                        $label = $orderItem?->label ?? $orderItem?->name ?? ('#'.$item->order_item_id);
                                    @endphp
                                    <li class="store-return-card__item">
                                        <div class="store-return-card__thumb" aria-hidden="true">
                                            @if ($imageUrl)
                                                <img src="{{ $imageUrl }}" alt="">
                                            @else
                                                <span class="store-return-card__thumb-placeholder"></span>
                                            @endif
                                        </div>
                                        <div class="store-return-card__item-body">
                                            <p class="store-return-card__item-title">{{ $label }}</p>
                                            <p class="store-return-card__meta">{{ $item->quantity }} ×</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($request->reason)
                                <p class="store-return-card__meta">{{ $request->reason }}</p>
                            @endif
                            @if ($request->staff_notes)
                                <p class="store-return-card__meta">
                                    {{ __('shipping::returns.customer_staff_notes') }}: {{ $request->staff_notes }}
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <p class="store-account-dashboard__hint">{{ __('shipping::returns.customer_refund_note') }}</p>
    </section>
</div>
