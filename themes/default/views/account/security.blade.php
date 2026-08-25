<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('customer.security.title')],
            ],
        ])

        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('customer.security.title') }}</h1>
            <p class="store-account-panel__lede">{{ __('customer.security.lede') }}</p>
        </header>

        @if (session('status'))
            <p class="store-note" role="status">{{ session('status') }}</p>
        @endif

        @if ($forced)
            <div class="store-note store-note--warning" role="status">
                <strong>{{ __('customer.security.required_title') }}</strong>
                <p>{{ __('customer.security.required_text') }}</p>
            </div>
        @endif

        <div class="store-account-form-stack">
            <x-ag.card>
                <x-ag.card.header>
                    <x-ag.card.title>{{ __('customer.security.two_factor_heading') }}</x-ag.card.title>
                    <x-ag.card.description>
                        @if ($enabled)
                            {{ __('customer.security.enabled_text') }}
                        @else
                            {{ __('customer.security.enable_text') }}
                        @endif
                    </x-ag.card.description>
                </x-ag.card.header>
                <x-ag.card.content>
                    @if ($enabled)
                        <div class="store-form-actions">
                            <button type="button" class="store-btn store-btn--outline" wire:click="regenerateRecoveryCodes">
                                {{ __('customer.security.regenerate_recovery') }}
                            </button>
                            <button type="button" class="store-btn store-btn--outline" wire:click="disable">
                                {{ __('customer.security.disable') }}
                            </button>
                        </div>
                    @elseif ($setupSecret !== '')
                        <p class="store-note">{{ __('customer.security.setup_text') }}</p>
                        @if ($qrSvg)
                            <div class="store-security-qr" aria-hidden="true">{!! $qrSvg !!}</div>
                        @endif
                        <p class="store-field__hint">{{ __('customer.security.manual_secret') }}</p>
                        <p><code>{{ $setupSecret }}</code></p>
                        <form class="store-auth__form" wire:submit="confirmSetup">
                            <div class="store-field">
                                <label class="store-label" for="totp-code">{{ __('customer.security.code') }}</label>
                                <input
                                    id="totp-code"
                                    class="store-input"
                                    type="text"
                                    inputmode="numeric"
                                    wire:model.live="code"
                                    autocomplete="one-time-code"
                                    required
                                >
                                @error('code') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                            </div>
                            <div class="store-form-actions">
                                <button type="submit" class="store-btn store-btn--primary">{{ __('customer.security.confirm') }}</button>
                            </div>
                        </form>
                    @else
                        <div class="store-form-actions">
                            <button type="button" class="store-btn store-btn--primary" wire:click="startSetup">
                                {{ __('customer.security.start') }}
                            </button>
                        </div>
                    @endif
                </x-ag.card.content>
            </x-ag.card>

            @if ($showingRecoveryCodes)
                <x-ag.card>
                    <x-ag.card.header>
                        <x-ag.card.title>{{ __('customer.security.recovery_heading') }}</x-ag.card.title>
                        <x-ag.card.description>{{ __('customer.security.recovery_text') }}</x-ag.card.description>
                    </x-ag.card.header>
                    <x-ag.card.content>
                        <ul class="store-security-recovery">
                            @foreach ($recoveryCodes as $recoveryCode)
                                <li><code>{{ $recoveryCode }}</code></li>
                            @endforeach
                        </ul>
                        <div class="store-form-actions">
                            <button type="button" class="store-btn store-btn--outline" wire:click="hideRecoveryCodes">
                                {{ __('customer.security.recovery_done') }}
                            </button>
                        </div>
                    </x-ag.card.content>
                </x-ag.card>
            @endif

            <x-ag.card>
                <x-ag.card.header>
                    <x-ag.card.title>{{ __('customer.security.sessions_heading') }}</x-ag.card.title>
                    <x-ag.card.description>{{ __('customer.security.sessions_lede') }}</x-ag.card.description>
                </x-ag.card.header>
                <x-ag.card.content>
                    @if (! $sessionsSupported)
                        <p class="store-note">{{ __('customer.security.sessions_unsupported') }}</p>
                    @elseif ($sessions->isEmpty())
                        <p class="store-note">{{ __('customer.security.sessions_empty') }}</p>
                    @else
                        <ul class="store-security-sessions">
                            @foreach ($sessions as $session)
                                <li class="store-security-session" wire:key="session-{{ $session['id'] }}">
                                    <div>
                                        <strong>{{ $session['device_label'] }}</strong>
                                        @if ($session['is_current'])
                                            <span class="store-badge">{{ __('customer.security.session_current') }}</span>
                                        @endif
                                        <p class="store-field__hint">
                                            {{ $session['ip_address'] ?? __('customer.security.session_unknown_ip') }}
                                            · {{ $session['last_activity']->diffForHumans() }}
                                        </p>
                                    </div>
                                    @unless ($session['is_current'])
                                        <button
                                            type="button"
                                            class="store-btn store-btn--outline"
                                            wire:click="revokeSession(@js($session['id']))"
                                            wire:confirm="{{ __('customer.security.session_revoke_confirm') }}"
                                        >
                                            {{ __('customer.security.session_revoke') }}
                                        </button>
                                    @endunless
                                </li>
                            @endforeach
                        </ul>
                        @if ($sessions->contains(fn (array $row): bool => ! $row['is_current']))
                            <div class="store-form-actions">
                                <button type="button" class="store-btn store-btn--outline" wire:click="revokeOtherSessions">
                                    {{ __('customer.security.revoke_others') }}
                                </button>
                            </div>
                        @endif
                    @endif
                </x-ag.card.content>
            </x-ag.card>
        </div>

        @include('livewire.admin.partials.confirm-password-modal')
    </section>
</div>
