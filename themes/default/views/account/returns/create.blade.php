<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
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

        @error('quantities')
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
                <div class="store-account-card-list" role="list">
                    @foreach ($lines as $line)
                        <article class="store-account-entry store-account-entry--form" role="listitem" wire:key="return-line-{{ $line['item']->id }}">
                            <div class="store-account-entry__body">
                                <p class="store-account-entry__title">{{ $line['item']->label }}</p>
                                <p class="store-account-entry__meta">{{ __('customer.account.quantity', ['count' => $line['item']->quantity]) }}</p>
                            </div>
                            <div class="store-field store-field--compact">
                                <label class="store-label" for="return-qty-{{ $line['item']->id }}">{{ __('shipping::returns.customer_quantity') }}</label>
                                <input
                                    id="return-qty-{{ $line['item']->id }}"
                                    class="store-input"
                                    type="number"
                                    min="0"
                                    max="{{ $line['returnable'] }}"
                                    wire:model="quantities.{{ $line['item']->id }}"
                                >
                            </div>
                        </article>
                    @endforeach
                </div>

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
