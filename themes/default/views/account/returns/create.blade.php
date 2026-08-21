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

        <header class="store-account-panel__header">
            <div>
                <h1 class="store-account-panel__title">
                    {{ __('shipping::returns.customer_create_title', ['number' => $order->number]) }}
                </h1>
                <p class="store-account-panel__lede">{{ __('shipping::returns.customer_create_lede') }}</p>
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
            <form wire:submit="submit" class="store-account-form">
                <div class="store-return-select" role="list">
                    @foreach ($lines as $line)
                        @php
                            $item = $line['item'];
                            $imageUrl = \App\Agovena\Media\ProductMedia::primaryUrl($item->product);
                        @endphp
                        <article class="store-return-select__row store-return-select__row--readonly" role="listitem" wire:key="return-line-{{ $item->id }}">
                            <div class="store-return-card__thumb" aria-hidden="true">
                                @if ($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="">
                                @else
                                    <span class="store-return-card__thumb-placeholder"></span>
                                @endif
                            </div>
                            <div class="store-return-select__copy">
                                <p class="store-return-card__item-title">{{ $item->label }}</p>
                                <p class="store-return-card__meta">
                                    {{ __('customer.account.quantity', ['count' => $line['returnable']]) }}
                                </p>
                            </div>
                        </article>
                    @endforeach
                </div>

                <p class="store-account-dashboard__hint">{{ __('shipping::returns.customer_full_order_note') }}</p>

                <div class="store-field">
                    <label class="store-label" for="return-reason">{{ __('shipping::returns.customer_reason') }}</label>
                    <textarea id="return-reason" class="store-input" rows="4" wire:model="reason"></textarea>
                    <p class="store-field__hint">{{ __('shipping::returns.customer_reason_hint') }}</p>
                </div>

                <div class="store-form-actions">
                    <button type="submit" class="store-btn store-btn--primary">
                        {{ __('shipping::returns.customer_submit') }}
                    </button>
                </div>
            </form>

            <p class="store-account-dashboard__hint">{{ __('shipping::returns.customer_refund_note') }}</p>
        @endif
    </section>
</div>
