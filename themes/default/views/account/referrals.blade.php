<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel" aria-labelledby="referrals-heading">
        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @if (! $referralsEnabled)
            <p class="store-alert store-alert--info" role="status">{{ __('customer.referrals.disabled') }}</p>
        @endif

        <header class="store-account-panel__header store-referral-hero">
            <div class="store-referral-hero__copy">
                <p class="store-account-panel__eyebrow">{{ __('customer.referrals.affiliate_eyebrow') }}</p>
                <h1 id="referrals-heading" class="store-account-panel__title">{{ __('customer.referrals.affiliate_title') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.referrals.affiliate_lede', ['percentage' => $headlinePercentage]) }}</p>
            </div>
            <div class="store-referral-hero__mark" aria-hidden="true">
                <span>{{ $headlinePercentage }}%</span>
                <small>{{ __('customer.referrals.per_first_purchase') }}</small>
            </div>
        </header>

        <section class="store-referral-stats" aria-label="{{ __('customer.referrals.stats_label') }}">
            <article class="store-referral-stat">
                <span class="store-referral-stat__icon" aria-hidden="true"><x-ag.icon name="share-2" :size="18" /></span>
                <strong class="store-referral-stat__value">{{ $linkClicks }}</strong>
                <span class="store-referral-stat__label">{{ __('customer.referrals.link_clicks') }}</span>
                <span class="store-referral-stat__hint">{{ __('customer.referrals.link_clicks_hint') }}</span>
            </article>
            <article class="store-referral-stat">
                <span class="store-referral-stat__icon" aria-hidden="true"><x-ag.icon name="shopping-bag" :size="18" /></span>
                <strong class="store-referral-stat__value">{{ $paidPurchases }}</strong>
                <span class="store-referral-stat__label">{{ __('customer.referrals.paid_purchases') }}</span>
            </article>
            <article class="store-referral-stat">
                <span class="store-referral-stat__icon" aria-hidden="true"><x-ag.icon name="coins" :size="18" /></span>
                <strong class="store-referral-stat__value">{{ $earnedRewards }}</strong>
                <span class="store-referral-stat__label">{{ __('customer.referrals.earned_rewards') }}</span>
            </article>
            <article class="store-referral-stat">
                <span class="store-referral-stat__icon" aria-hidden="true"><x-ag.icon name="calendar" :size="18" /></span>
                <strong class="store-referral-stat__value">{{ $headlineWindowDays }}</strong>
                <span class="store-referral-stat__label">{{ __('customer.referrals.window_label') }}</span>
            </article>
        </section>

        @if ($referralLink !== null || $referralsEnabled)
            <section class="store-referral-link" x-data="{ copied: false }" aria-labelledby="referral-link-heading">
                @if ($referralLink !== null)
                    <div class="store-referral-link__copy">
                        <p class="store-account-panel__eyebrow">{{ __('customer.referrals.share_eyebrow') }}</p>
                        <h2 id="referral-link-heading">{{ __('customer.referrals.share_heading') }}</h2>
                        <p>{{ __('customer.referrals.share_lede') }}</p>
                    </div>
                    <div class="store-referral-link__control">
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
                    <div class="store-referral-link__copy">
                        <p class="store-account-panel__eyebrow">{{ __('customer.referrals.share_eyebrow') }}</p>
                        <h2 id="referral-link-heading">{{ __('customer.referrals.share_heading') }}</h2>
                        <p>{{ __('customer.referrals.create_lede') }}</p>
                    </div>
                    <form wire:submit="createCode" class="store-referral-link__control" aria-label="{{ __('customer.referrals.create_heading') }}">
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
