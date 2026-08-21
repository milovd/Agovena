<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('customer.tickets.heading'), 'url' => route('customer.tickets.index')],
                ['label' => __('customer.tickets.create')],
            ],
        ])

        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="mail" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">{{ __('customer.tickets.create_title') }}</h1>
                    <p class="store-support-hero__lede">{{ __('customer.tickets.create_lede') }}</p>
                </div>
            </div>
        </header>

        <form class="store-account-form store-support-form" wire:submit="save">
            <div class="store-field">
                <label class="store-label" for="ticket-subject">{{ __('customer.tickets.subject') }}</label>
                <input id="ticket-subject" class="store-input" wire:model="subject" required>
                @error('subject') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-support-form__row">
                <div class="store-field">
                    <label class="store-label" for="ticket-priority">{{ __('customer.tickets.priority_label') }}</label>
                    <select id="ticket-priority" class="store-input" wire:model="priority">
                        @foreach (\App\Enums\TicketPriority::cases() as $priority)
                            <option value="{{ $priority->value }}">{{ __('customer.tickets.priority.'.$priority->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="store-field">
                    <label class="store-label" for="ticket-order">{{ __('customer.tickets.order') }}</label>
                    <select id="ticket-order" class="store-input" wire:model="order_id">
                        <option value="">{{ __('customer.tickets.no_order') }}</option>
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}">{{ $order->number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="store-field">
                <label class="store-label" for="ticket-body">{{ __('customer.tickets.message') }}</label>
                <textarea id="ticket-body" class="store-input" rows="8" wire:model="body" required></textarea>
                @error('body') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-form-actions">
                <button class="store-btn store-btn--primary" type="submit">{{ __('customer.tickets.submit') }}</button>
            </div>
        </form>
    </section>
</div>
