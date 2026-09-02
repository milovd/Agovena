<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel" aria-labelledby="referrals-heading">
        <header class="store-account-panel__header">
            <div>
                <p class="store-account-panel__eyebrow">{{ __('customer.account.nav_referrals') }}</p>
                <h1 id="referrals-heading" class="store-account-panel__title">{{ __('customer.referrals.title') }}</h1>
                <p class="store-account-panel__lede">{{ __('customer.referrals.lede') }}</p>
            </div>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        @if (! $referralsEnabled)
            <p class="store-alert store-alert--info" role="status">{{ __('customer.referrals.disabled') }}</p>
        @endif

        <div class="store-account-cards" aria-label="{{ __('customer.referrals.balance_heading') }}">
            <a class="store-account-card store-account-card--link" href="{{ route('customer.credits') }}">
                <p class="store-account-card__label">{{ __('customer.referrals.balance_heading') }}</p>
                <strong class="store-account-card__value">{{ $accountBalance }}</strong>
                <p class="store-account-card__hint">{{ __('customer.referrals.balance_lede') }}</p>
            </a>
            <div class="store-account-card">
                <p class="store-account-card__label">{{ __('customer.referrals.default_rate') }}</p>
                <strong class="store-account-card__value">{{ $defaultRewardPercentage }}%</strong>
                <p class="store-account-card__hint">{{ __('customer.referrals.rate_value', ['percentage' => $defaultRewardPercentage]) }}</p>
            </div>
        </div>

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
                        <article class="store-account-entry">
                            <div class="store-account-entry__body">
                                <h3 class="store-account-entry__title">{{ $code->code }}</h3>
                                <p class="store-account-entry__meta">{{ $code->uses_count }} {{ __('customer.referrals.uses') }} · {{ $percentage }}%</p>
                            </div>
                            <span class="ag-badge {{ $code->is_active ? 'ag-badge--success' : 'ag-badge--muted' }}">
                                {{ $code->is_active ? __('customer.referrals.active') : __('customer.referrals.inactive') }}
                            </span>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="store-account-panel__section" aria-labelledby="referral-activity-heading">
            <div class="store-account-panel__section-head">
                <h2 id="referral-activity-heading">{{ __('customer.referrals.activity_heading') }}</h2>
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
