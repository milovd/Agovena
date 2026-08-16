<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <p class="store-account-panel__back">
                <a href="{{ route('customer.returns') }}">{{ __('shipping::returns.customer_title') }}</a>
            </p>
            <h1 class="store-account-panel__title">
                {{ __('shipping::returns.customer_create_title', ['number' => $order->number]) }}
            </h1>
            <p class="store-account-panel__lede">{{ __('shipping::returns.customer_create_lede') }}</p>
        </header>

        @error('quantities')
            <p class="store-alert store-alert--error" role="alert">{{ $message }}</p>
        @enderror

        @if ($lines->isEmpty())
            <p class="store-muted">{{ __('shipping::returns.customer_nothing_returnable') }}</p>
        @else
            <form wire:submit="submit" class="store-account-panel__section">
                <ul class="store-order-items" role="list">
                    @foreach ($lines as $line)
                        <li class="store-order-items__row" wire:key="return-line-{{ $line['item']->id }}">
                            <div>
                                <strong>{{ $line['item']->label }}</strong>
                                <p>{{ __('customer.account.quantity', ['count' => $line['item']->quantity]) }}</p>
                            </div>
                            <label>
                                {{ __('shipping::returns.customer_quantity') }}
                                <input
                                    type="number"
                                    min="0"
                                    max="{{ $line['returnable'] }}"
                                    wire:model="quantities.{{ $line['item']->id }}"
                                >
                            </label>
                        </li>
                    @endforeach
                </ul>

                <label>
                    {{ __('shipping::returns.customer_reason') }}
                    <textarea rows="4" wire:model="reason"></textarea>
                </label>
                <p class="store-muted">{{ __('shipping::returns.customer_reason_hint') }}</p>

                <button type="submit" class="store-btn store-btn--primary">
                    {{ __('shipping::returns.customer_submit') }}
                </button>
            </form>

            <p class="store-muted">{{ __('shipping::returns.customer_refund_note') }}</p>
        @endif
    </section>
</div>
