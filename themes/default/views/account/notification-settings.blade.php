<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel" aria-labelledby="notification-settings-heading">
        <header class="store-account-panel__header">
            <div>
                <p class="store-account-panel__eyebrow">{{ __('customer.notifications.kicker') }}</p>
                <h1 id="notification-settings-heading" class="store-account-panel__title">{{ __('customer.notifications.settings_title') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.notifications.settings_lede') }}</p>
            </div>
        </header>

        @if (session('status'))
            <p class="store-flash" role="status">{{ session('status') }}</p>
        @endif

        <section class="store-notification-push" aria-labelledby="notification-push-heading">
            <div class="store-notification-push__copy">
                <div class="store-notification-push__icon" aria-hidden="true"><x-ag.icon name="bell" :size="24" /></div>
                <div>
                    <h2 id="notification-push-heading">{{ __('customer.notifications.push_heading') }}</h2>
                    <p>{{ __('customer.notifications.push_lede') }}</p>
                </div>
            </div>
            <div
                class="store-notification-push__controls"
                x-data="storefrontPushInstaller"
                data-configured="{{ $pushConfigured ? 'true' : 'false' }}"
                data-config-url="{{ e(route('customer.notifications.push-config')) }}"
                data-subscribe-url="{{ e(route('customer.notifications.push-subscription')) }}"
                data-unsubscribe-url="{{ e(route('customer.notifications.push-subscription')) }}"
                data-messages="{{ e(json_encode([
                    'unsupported' => __('customer.notifications.push_unsupported'),
                    'notConfigured' => __('customer.notifications.push_not_configured'),
                    'permissionDenied' => __('customer.notifications.push_permission_denied'),
                    'installed' => __('customer.notifications.push_installed'),
                    'removed' => __('customer.notifications.push_removed'),
                    'failed' => __('customer.notifications.push_failed'),
                ])) }}"
            >
                <p class="store-notification-push__status" role="status" x-text="status"></p>
                <button
                    id="notification-push-install"
                    class="store-btn store-btn--primary"
                    type="button"
                    x-show="!subscribed && supported && configured"
                    x-cloak
                    @click="install()"
                    :disabled="busy || !supported || !configured"
                >
                    {{ __('customer.notifications.install_push') }}
                </button>
                <button
                    class="store-btn store-btn--outline"
                    type="button"
                    x-show="subscribed"
                    x-cloak
                    @click="remove()"
                    :disabled="busy"
                >
                    {{ __('customer.notifications.remove_push') }}
                </button>
            </div>
        </section>

        <form class="store-notification-preferences" wire:submit="savePreferences">
            <div class="store-notification-preferences__header">
                <div>
                    <h2>{{ __('customer.notifications.preferences_heading') }}</h2>
                    <p>{{ __('customer.notifications.preferences_lede') }}</p>
                </div>
                <button
                    class="store-btn store-btn--primary"
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="savePreferences"
                >
                    <span wire:loading.remove wire:target="savePreferences">{{ __('customer.notifications.save') }}</span>
                    <span wire:loading wire:target="savePreferences">{{ __('customer.notifications.saving') }}</span>
                </button>
            </div>

            <div class="store-notification-preferences__table" role="table" aria-label="{{ __('customer.notifications.preferences_heading') }}">
                <div class="store-notification-preferences__row store-notification-preferences__row--head" role="row">
                    <span role="columnheader">{{ __('customer.notifications.event') }}</span>
                    <span role="columnheader">{{ __('customer.notifications.channel_in_app') }}</span>
                    <span role="columnheader">{{ __('customer.notifications.channel_push') }}</span>
                    <span role="columnheader">{{ __('customer.notifications.channel_mail') }}</span>
                </div>
                @foreach ($definitions as $definition)
                    @php $canChoose = $userChoice[$definition->key] ?? false; @endphp
                    <div class="store-notification-preferences__row" role="row" wire:key="notification-preference-{{ $definition->key }}">
                        <span class="store-notification-preferences__event" role="cell">
                            <strong>{{ __($definition->label) }}</strong>
                            <small>{{ $canChoose ? $definition->key : __('customer.notifications.managed_by_store') }}</small>
                        </span>
                        <label class="store-switch" role="cell">
                            <span class="visually-hidden">{{ __('customer.notifications.channel_in_app') }}: {{ __($definition->label) }}</span>
                            <input type="checkbox" wire:model="preferences.{{ $definition->key }}.in_app_enabled" @disabled(! $canChoose)>
                            <span class="store-switch__track" aria-hidden="true"></span>
                        </label>
                        <label class="store-switch" role="cell">
                            <span class="visually-hidden">{{ __('customer.notifications.channel_push') }}: {{ __($definition->label) }}</span>
                            <input type="checkbox" wire:model="preferences.{{ $definition->key }}.push_enabled" @disabled(! $canChoose)>
                            <span class="store-switch__track" aria-hidden="true"></span>
                        </label>
                        <label class="store-switch" role="cell">
                            <span class="visually-hidden">{{ __('customer.notifications.channel_mail') }}: {{ __($definition->label) }}</span>
                            <input type="checkbox" wire:model="preferences.{{ $definition->key }}.mail_enabled" @disabled(! $canChoose)>
                            <span class="store-switch__track" aria-hidden="true"></span>
                        </label>
                    </div>
                @endforeach
            </div>
        </form>
    </section>
</div>
