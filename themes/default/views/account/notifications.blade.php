<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel" aria-labelledby="customer-notifications-heading">
        <header class="store-account-panel__header">
            <div>
                <p class="store-account-panel__eyebrow">{{ __('customer.notifications.kicker') }}</p>
                <h1 id="customer-notifications-heading" class="store-account-panel__title">{{ __('customer.notifications.title') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.notifications.lede') }}</p>
            </div>
            @if ($unreadCount > 0)
                <button type="button" class="store-btn store-btn--outline" wire:click="markAllRead">
                    {{ __('customer.notifications.mark_all_read') }}
                </button>
            @endif
        </header>

        <p class="store-notification-state store-notification-state--loading" wire:loading.delay role="status">
            {{ __('customer.notifications.loading') }}
        </p>
        <p class="store-notification-state store-notification-state--offline" wire:offline role="alert">
            {{ __('customer.notifications.offline') }}
        </p>

        @if ($notifications->isEmpty())
            <x-ag.empty :title="__('customer.notifications.empty')">
                <x-slot:icon>
                    <x-ag.icon name="bell" :size="24" />
                </x-slot:icon>
                <x-slot:description>{{ __('customer.notifications.empty_hint') }}</x-slot:description>
            </x-ag.empty>
        @else
            <div class="store-notification-list" aria-live="polite">
                @foreach ($notifications as $notification)
                    <article class="store-notification-card {{ $notification->read_at === null ? 'is-unread' : '' }}" wire:key="notification-{{ $notification->id }}">
                        <div class="store-notification-card__indicator" aria-hidden="true"></div>
                        <div class="store-notification-card__copy">
                            <div class="store-notification-card__heading">
                                <h2>{{ $notification->title }}</h2>
                                <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->diffForHumans() }}</time>
                            </div>
                            <p>{{ $notification->body }}</p>
                            <div class="store-notification-card__actions">
                                @if ($notification->action_url)
                                    <a class="store-btn store-btn--outline" href="{{ $notification->action_url }}" wire:click="markRead({{ $notification->id }})">
                                        {{ __('customer.notifications.open_action') }}
                                    </a>
                                @endif
                                @if ($notification->read_at === null)
                                    <button type="button" class="store-link-button" wire:click="markRead({{ $notification->id }})">
                                        {{ __('customer.notifications.mark_read') }}
                                    </button>
                                @else
                                    <span class="store-notification-card__read">{{ __('customer.notifications.read') }}</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="store-account__pagination">{{ $notifications->links() }}</div>
        @endif
    </section>
</div>
