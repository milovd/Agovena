<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel" aria-labelledby="referrals-heading">
        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @if (! $referralsEnabled)
            <p class="store-alert store-alert--info" role="status">{{ __('customer.referrals.disabled') }}</p>
        @endif

        <header class="store-referral-hero">
            <div class="store-referral-hero__copy">
                <p class="store-account-panel__eyebrow">{{ __('customer.referrals.affiliate_eyebrow') }}</p>
                <h1 id="referrals-heading" class="store-account-panel__title">{{ __('customer.referrals.affiliate_title') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.referrals.affiliate_lede', ['percentage' => $headlinePercentage]) }}</p>
            </div>
            <div class="store-referral-hero__terms" aria-label="{{ __('customer.referrals.stats_label') }}">
                <div class="store-referral-hero__term">
                    <strong>{{ $headlinePercentage }}%</strong>
                    <span>{{ __('customer.referrals.per_first_purchase') }}</span>
                </div>
                <div class="store-referral-hero__term">
                    <strong>{{ $headlineWindowDays }}</strong>
                    <span>{{ __('customer.referrals.window_label') }}</span>
                </div>
            </div>
        </header>

        <section class="store-referral-performance" aria-labelledby="referral-performance-heading">
            <h2 id="referral-performance-heading">{{ __('customer.referrals.stats_label') }}</h2>
            <dl class="store-referral-stats">
                <div class="store-referral-stat">
                    <dt>{{ __('customer.referrals.paid_purchases') }}</dt>
                    <dd>{{ $paidPurchases }}</dd>
                </div>
                <div class="store-referral-stat store-referral-stat--accent">
                    <dt>{{ __('customer.referrals.earned_rewards') }}</dt>
                    <dd>{{ $earnedRewards }}</dd>
                </div>
                <div class="store-referral-stat">
                    <dt>{{ __('customer.referrals.link_clicks') }}</dt>
                    <dd>{{ $linkClicks }}</dd>
                </div>
            </dl>
        </section>

        @if ($referralLink !== null || $referralsEnabled)
            <section class="store-referral-share" x-data="{ copied: false }" aria-labelledby="referral-share-heading">
                <div class="store-referral-share__header">
                    <div>
                        <p class="store-account-panel__eyebrow">{{ __('customer.referrals.share_eyebrow') }}</p>
                        <h2 id="referral-share-heading">{{ __('customer.referrals.share_heading') }}</h2>
                    </div>
                    @if ($primaryCode !== null)
                        <span class="store-referral-share__code">{{ $primaryCode->code }}</span>
                    @endif
                </div>

                @if ($referralLink !== null)
                    <p class="store-referral-share__lede">{{ __('customer.referrals.share_lede') }}</p>
                    <div class="store-referral-share__control">
                        <label class="sr-only" for="referral-share-link">{{ __('customer.referrals.share_link') }}</label>
                        <input id="referral-share-link" class="store-input" type="text" value="{{ $referralLink }}" readonly x-ref="link">
                        <button
                            type="button"
                            class="store-btn store-btn--secondary"
                            @click="navigator.clipboard.writeText($refs.link.value).then(() => { copied = true; setTimeout(() => copied = false, 1800) })"
                            :aria-label="copied ? '{{ __('customer.referrals.copied') }}' : '{{ __('customer.referrals.copy_link') }}'"
                        >
                            <x-ag.icon name="file-text" :size="16" aria-hidden="true" />
                            <span x-text="copied ? '{{ __('customer.referrals.copied') }}' : '{{ __('customer.referrals.copy_link') }}'">{{ __('customer.referrals.copy_link') }}</span>
                        </button>
                    </div>
                @elseif ($referralsEnabled)
                    <p class="store-referral-share__lede">{{ __('customer.referrals.create_lede') }}</p>
                    <form wire:submit="createCode" class="store-referral-create" aria-label="{{ __('customer.referrals.create_heading') }}">
                        <div class="store-field">
                            <label class="store-label" for="referral-code">{{ __('customer.referrals.code') }}</label>
                            <input id="referral-code" class="store-input" wire:model="newCode" type="text" autocomplete="off" required>
                            <p class="store-field__hint">{{ __('customer.referrals.code_help') }}</p>
                            @error('newCode') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <button class="store-btn store-btn--primary" type="submit">{{ __('customer.referrals.create') }}</button>
                    </form>
                @endif
            </section>
        @endif
    </section>
</div>
