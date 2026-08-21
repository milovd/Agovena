<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('customer.profile.heading') }}</h1>
            <p class="store-account-panel__lede">{{ __('customer.profile.lede') }}</p>
        </header>

        <div class="store-account-form-stack">
            <x-ag.card>
                <x-ag.card.header>
                    <x-ag.card.title>{{ __('customer.profile.identity_heading') }}</x-ag.card.title>
                    <x-ag.card.description>{{ __('customer.profile.identity_lede') }}</x-ag.card.description>
                </x-ag.card.header>
                <x-ag.card.content>
                    <form class="store-auth__form" wire:submit="saveProfile">
                        <div class="store-field">
                            <label class="store-label" for="profile-name">{{ __('customer.auth.name') }}</label>
                            <input id="profile-name" class="store-input" type="text" wire:model="name" autocomplete="name" required>
                            @error('name') <p class="store-field__error">{{ $message }}</p> @enderror
                        </div>

                        <div class="store-field">
                            <label class="store-label" for="profile-email">{{ __('customer.auth.email') }}</label>
                            <input id="profile-email" class="store-input" type="email" wire:model="email" autocomplete="username" required>
                            <p class="store-field__hint">
                                <strong>{{ $emailVerified ? __('customer.profile.email_verified') : __('customer.profile.email_needs_verification') }}</strong>
                                {{ $emailVerified ? __('customer.profile.email_verified_hint') : __('customer.profile.email_needs_verification_hint') }}
                            </p>
                            @error('email') <p class="store-field__error">{{ $message }}</p> @enderror
                        </div>

                        @include('partials.custom-property-fields', ['actor' => 'customer'])

                        <div class="store-form-actions">
                            <button class="store-btn store-btn--primary" type="submit">{{ __('customer.profile.save') }}</button>
                        </div>
                    </form>
                </x-ag.card.content>
            </x-ag.card>

            <x-ag.card>
                <x-ag.card.header>
                    <x-ag.card.title>{{ __('customer.profile.password_heading') }}</x-ag.card.title>
                    <x-ag.card.description>{{ __('customer.profile.password_lede') }}</x-ag.card.description>
                </x-ag.card.header>
                <x-ag.card.content>
                    <form class="store-auth__form" wire:submit="changePassword">
                        <div class="store-field">
                            <label class="store-label" for="current-password">{{ __('customer.profile.current_password') }}</label>
                            <input id="current-password" class="store-input" type="password" wire:model="current_password" autocomplete="current-password" required>
                            @error('current_password') <p class="store-field__error">{{ $message }}</p> @enderror
                        </div>

                        <div class="store-field">
                            <label class="store-label" for="new-password">{{ __('customer.profile.new_password') }}</label>
                            <input id="new-password" class="store-input" type="password" wire:model="password" autocomplete="new-password" required>
                            @error('password') <p class="store-field__error">{{ $message }}</p> @enderror
                        </div>

                        <div class="store-field">
                            <label class="store-label" for="new-password-confirmation">{{ __('customer.auth.password_confirmation') }}</label>
                            <input id="new-password-confirmation" class="store-input" type="password" wire:model="password_confirmation" autocomplete="new-password" required>
                        </div>

                        <div class="store-form-actions">
                            <button class="store-btn store-btn--outline" type="submit">{{ __('customer.profile.change_password') }}</button>
                        </div>
                    </form>
                </x-ag.card.content>
            </x-ag.card>

            <x-ag.card id="addresses">
                <x-ag.card.header>
                    <x-ag.card.title>{{ __('customer.addresses.heading') }}</x-ag.card.title>
                    <x-ag.card.description>{{ __('customer.addresses.lede') }}</x-ag.card.description>
                </x-ag.card.header>
                <x-ag.card.content>
                    <livewire:customer.account.addresses :embedded="true" />
                </x-ag.card.content>
            </x-ag.card>

            <x-ag.card>
                <x-ag.card.header>
                    <x-ag.card.title>{{ __('customer.privacy.heading') }}</x-ag.card.title>
                    <x-ag.card.description>{{ __('customer.privacy.lede') }}</x-ag.card.description>
                </x-ag.card.header>
                <x-ag.card.content>
                    <div class="store-auth__actions">
                        <button class="store-btn store-btn--outline" type="button" wire:click="exportData">
                            {{ __('customer.privacy.export') }}
                        </button>
                        @if ($deletionRequested)
                            <p class="store-note">{{ __('customer.privacy.request_pending') }}</p>
                        @else
                            <button
                                class="store-btn store-btn--outline"
                                type="button"
                                wire:click="requestDeletion"
                                wire:confirm="{{ __('customer.privacy.delete_confirm') }}"
                            >{{ __('customer.privacy.request_deletion') }}</button>
                        @endif
                    </div>
                </x-ag.card.content>
            </x-ag.card>
        </div>
    </section>
</div>
