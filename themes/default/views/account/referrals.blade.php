<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel" aria-labelledby="referrals-heading">
        <header class="store-account-panel__header store-referral-hero">
            <div class="store-referral-hero__copy">
                <p class="store-account-panel__eyebrow">{{ __('customer.referrals.affiliate_eyebrow') }}</p>
                <h1 id="referrals-heading" class="store-account-panel__title">{{ __('customer.referrals.affiliate_title') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.referrals.affiliate_lede', ['percentage' => $headlinePercentage]) }}</p>
            </div>
            <div class="store-referral-hero__mark" aria-hidden="true">
                <span>{{ $headlinePercentage }}%</span>
                <small>{{ __('customer.referrals.of_first_purchase') }}</small>
            </div>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @if (! $referralsEnabled)
            <p class="store-alert store-alert--info" role="status">{{ __('customer.referrals.disabled') }}</p>
        @endif

        <section class="store-referral-stats" aria-label="{{ __('customer.referrals.stats_label') }}">
            <article class="store-referral-stat">
                <span class="store-referral-stat__icon" aria-hidden="true"><x-ag.icon name="share-2" :size="18" /></span>
                <strong class="store-referral-stat__value">{{ $linkClicks }}</strong>
                <span class="store-referral-stat__label">{{ __('customer.referrals.link_clicks') }}</span>
            </article>
            <article class="store-referral-stat">
                <span class="store-referral-stat__icon" aria-hidden="true"><x-ag.icon name="users" :size="18" /></span>
                <strong class="store-referral-stat__value">{{ $uniqueVisitors }}</strong>
                <span class="store-referral-stat__label">{{ __('customer.referrals.link_visits') }}</span>
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
        </section>

        @if ($referralLink !== null)
            <section class="store-referral-link" x-data="{ copied: false }" aria-labelledby="referral-link-heading">
                <div class="store-referral-link__copy">
                    <p class="store-account-panel__eyebrow">{{ __('customer.referrals.share_eyebrow') }}</p>
                    <h2 id="referral-link-heading">{{ __('customer.referrals.share_heading') }}</h2>
                    <p>{{ __('customer.referrals.share_lede', ['days' => $headlineWindowDays, 'percentage' => $headlinePercentage]) }}</p>
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
                        <x-ag.icon name="share-2" :size="16" aria-hidden="true" />
                        <span x-text="copied ? '{{ __('customer.referrals.copied') }}' : '{{ __('customer.referrals.copy_link') }}'">{{ __('customer.referrals.copy_link') }}</span>
                    </button>
                </div>
            </section>
        @endif

        <section class="store-account-panel__section" aria-labelledby="referral-code-heading">
            <div class="store-account-panel__section-head">
                <div>
                    <h2 id="referral-code-heading">{{ __('customer.referrals.codes_heading') }}</h2>
                    <p class="store-account-panel__lede">{{ __('customer.referrals.create_lede') }}</p>
                </div>
            </div>

            @if ($referralsEnabled && $codes->isEmpty())
                <form wire:submit="createCode" class="store-auth__form" aria-label="{{ __('customer.referrals.create_heading') }}">
                    <div class="store-field">
                        <label class="store-label" for="referral-code">{{ __('customer.referrals.code') }}</label>
                        <input id="referral-code" class="store-input" wire:model="newCode" type="text" autocomplete="off" required>
                        <p class="store-field__hint">{{ __('customer.referrals.code_help') }}</p>
                        @error('newCode') <p class="store-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="store-form-actions">
                        <button class="store-btn store-btn--primary" type="submit">{{ __('customer.referrals.create') }}</button>
                    </div>
                </form>
            @else
                <div class="store-account-card-list">
                    @foreach ($codes as $code)
                        @php $percentage = $code->reward_percentage ?? $defaultRewardPercentage; @endphp
                        <article class="store-account-entry store-referral-code">
                            <div class="store-account-entry__body">
                                <div class="store-referral-code__heading">
                                    <h3 class="store-account-entry__title">{{ $code->code }}</h3>
                                    <span class="ag-badge {{ $code->is_active ? 'ag-badge--success' : 'ag-badge--muted' }}">
                                        {{ $code->is_active ? __('customer.referrals.active') : __('customer.referrals.inactive') }}
                                    </span>
                                </div>
                                <p class="store-account-entry__meta">{{ $percentage }}% · {{ __('customer.referrals.window_days', ['days' => $code->effective_window_days]) }}</p>
                                <a class="store-referral-code__link" href="{{ $code->referral_link }}" target="_blank" rel="noreferrer">{{ $code->referral_link }}</a>
                            </div>
                            <div class="store-referral-code__stats" aria-label="{{ __('customer.referrals.code_stats') }}">
                                <span><strong>{{ $code->visits_count }}</strong> {{ __('customer.referrals.link_visits') }}</span>
                                <span><strong>{{ $code->paid_purchases_count }}</strong> {{ __('customer.referrals.paid_purchases') }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="store-account-panel__section" aria-labelledby="referral-activity-heading">
            <div class="store-account-panel__section-head">
                <div>
                    <h2 id="referral-activity-heading">{{ __('customer.referrals.activity_heading') }}</h2>
                    <p class="store-account-panel__lede">{{ __('customer.referrals.activity_lede') }}</p>
                </div>
            </div>
            <div class="store-account-card-list">
                @forelse ($attributions as $attribution)
                    @php
                        $statusClass = match ($attribution->status) {
                            'posted' => 'ag-badge--success',
                            'review' => 'ag-badge--warning',
                            'void' => 'ag-badge--danger',
                            default => 'ag-badge--info',
                        };
                    @endphp
                    <article class="store-account-entry">
                        <div class="store-account-entry__body">
                            <h3 class="store-account-entry__title">{{ $attribution->order?->number ?? __('customer.referrals.order') }}</h3>
                            <p class="store-account-entry__meta">{{ $attribution->code_snapshot }} · {{ $attribution->reward_percentage ?? 0 }}%</p>
                        </div>
                        <div class="store-account-entry__end">
                            <strong>{{ \App\Support\MoneyFormatter::format($attribution->reward_amount, $attribution->reward_currency ?? $currency) }}</strong>
                            <span class="ag-badge {{ $statusClass }}">{{ __('customer.referrals.statuses.'.$attribution->status) }}</span>
                        </div>
                    </article>
                @empty
                    <p class="store-account-panel__empty">{{ __('customer.referrals.empty_activity') }}</p>
                @endforelse
            </div>
        </section>
    </section>
</div>
