<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'back' => [
                'url' => route('customer.returns'),
                'label' => __('shipping::returns.customer_back'),
            ],
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('shipping::returns.customer_title'), 'url' => route('customer.returns')],
                ['label' => $order->number],
            ],
        ])

        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="rotate-ccw" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">
                        {{ __('shipping::returns.customer_create_title', ['number' => $order->number]) }}
                    </h1>
                    <p class="store-support-hero__lede">{{ __('shipping::returns.customer_create_lede') }}</p>
                </div>
            </div>
        </header>

        @error('form')
            <p class="store-alert store-alert--error" role="alert">{{ $message }}</p>
        @enderror

        @if ($lines->isEmpty())
            <x-ag.empty :title="__('shipping::returns.customer_nothing_returnable')">
                <x-slot:icon>
                    <x-ag.icon name="package" :size="22" />
                </x-slot:icon>
            </x-ag.empty>
        @else
            <form wire:submit="submit" class="store-return-request">
                <section class="store-return-request__panel" aria-labelledby="return-items-heading">
                    <header class="store-return-request__panel-head">
                        <div>
                            <h2 id="return-items-heading" class="store-return-request__panel-title">
                                {{ __('shipping::returns.customer_items_heading') }}
                            </h2>
                            <p class="store-return-request__panel-lede">{{ __('shipping::returns.customer_full_order_note') }}</p>
                        </div>
                        <span class="store-return-request__badge">
                            {{ trans_choice('shipping::returns.customer_item_count', $lines->count(), ['count' => $lines->count()]) }}
                        </span>
                    </header>

                    <ul class="store-return-request__items" role="list">
                        @foreach ($lines as $line)
                            @php
                                $item = $line['item'];
                                $imageUrl = \App\Agovena\Media\ProductMedia::primaryUrl($item->product);
                            @endphp
                            <li class="store-return-request__item" wire:key="return-line-{{ $item->id }}">
                                <div class="store-return-request__thumb" aria-hidden="true">
                                    @if ($imageUrl)
                                        <img src="{{ $imageUrl }}" alt="">
                                    @else
                                        <span class="store-return-card__thumb-placeholder"></span>
                                    @endif
                                </div>
                                <div class="store-return-request__item-copy">
                                    <p class="store-return-request__item-title">{{ $item->label }}</p>
                                    <p class="store-return-request__item-meta">
                                        {{ __('customer.account.quantity', ['count' => $line['returnable']]) }}
                                    </p>
                                </div>
                                <span class="store-return-request__included">
                                    <x-ag.icon name="check" :size="14" />
                                    {{ __('shipping::returns.customer_included') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <aside class="store-return-request__aside">
                    <div class="store-return-request__panel store-return-request__panel--aside">
                        <h2 class="store-return-request__panel-title">{{ __('shipping::returns.customer_reason') }}</h2>
                        <p class="store-return-request__panel-lede">{{ __('shipping::returns.customer_reason_hint') }}</p>

                        <div class="store-field">
                            <label class="visually-hidden" for="return-reason">{{ __('shipping::returns.customer_reason') }}</label>
                            <textarea id="return-reason" class="store-input" rows="6" wire:model="reason" placeholder="{{ __('shipping::returns.customer_reason_placeholder') }}"></textarea>
                        </div>

                        <div class="store-form-actions">
                            <button type="submit" class="store-btn store-btn--primary store-btn--block">
                                {{ __('shipping::returns.customer_submit') }}
                            </button>
                        </div>

                        <p class="store-return-request__footnote">{{ __('shipping::returns.customer_refund_note') }}</p>
                    </div>
                </aside>
            </form>
        @endif
    </section>
</div>
