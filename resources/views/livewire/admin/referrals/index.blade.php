<div class="admin-page" id="referrals-overview">
    <header class="admin-page__header">
        <div>
            <p class="admin-page__eyebrow">{{ __('admin.referrals.eyebrow') }}</p>
            <h1 class="admin-page__heading">{{ __('admin.referrals.title') }}</h1>
            <p class="admin-page__lede">{{ __('admin.referrals.lede') }}</p>
        </div>
        @can('referrals.manage')
            <div class="admin-page__actions">
                <button type="button" class="ag-btn ag-btn--primary" wire:click="createCode">
                    {{ __('admin.referrals.add_code') }}
                </button>
            </div>
        @endcan
    </header>

    @if (session('status'))
        <div class="ag-alert ag-alert--success" role="status" aria-live="polite">
            <div class="ag-alert__body">{{ session('status') }}</div>
        </div>
    @endif

    @if ($showCodeForm)
        <section class="admin-panel" aria-labelledby="new-referral-code-heading">
            <h2 id="new-referral-code-heading" class="admin-panel__title">{{ __('admin.referrals.new_code') }}</h2>
            <form wire:submit="saveCode" class="ag-form" novalidate>
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field ag-combobox">
                        <label class="ag-field__label" for="referral-code-customer">{{ __('admin.referrals.customer') }}</label>
                        <input id="referral-code-customer" class="ag-input" type="search" wire:model.live.debounce.300ms="customerSearch" role="combobox" aria-autocomplete="list" aria-controls="referral-customer-options" aria-expanded="{{ $customerId === null && $customers->isNotEmpty() ? 'true' : 'false' }}" placeholder="{{ __('admin.referrals.customer_placeholder') }}" autocomplete="off" required>
                        <p class="ag-field__help">{{ __('admin.referrals.customer_help') }}</p>
                        @if ($customerId === null && $customers->isNotEmpty())
                            <div id="referral-customer-options" class="ag-combobox__options" role="listbox">
                                @foreach ($customers as $customer)
                                    <button type="button" class="ag-combobox__option" role="option" wire:click="selectCustomer({{ $customer->id }})">
                                        <span>{{ $customer->name }}</span>
                                        <span class="ag-combobox__option-email">{{ $customer->email }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif ($customerSearch !== '' && $customerId === null)
                            <p class="ag-field__help">{{ __('admin.referrals.no_customers') }}</p>
                        @endif
                        @error('customerId') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="referral-code-value">{{ __('admin.referrals.code') }}</label>
                        <input id="referral-code-value" class="ag-input" type="text" wire:model="newCode" required maxlength="64" autocomplete="off">
                        <p class="ag-field__help">{{ __('admin.referrals.code_help') }}</p>
                        @error('newCode') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="referral-code-max-uses">{{ __('admin.referrals.max_uses') }}</label>
                        <input id="referral-code-max-uses" class="ag-input" type="number" min="1" wire:model.number="maxUses">
                        <p class="ag-field__help">{{ __('admin.referrals.max_uses_help') }}</p>
                        @error('maxUses') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="referral-code-expires-at">{{ __('admin.referrals.expires_at') }}</label>
                        <input id="referral-code-expires-at" class="ag-input" type="datetime-local" wire:model="expiresAt">
                        <p class="ag-field__help">{{ __('admin.referrals.expires_at_help') }}</p>
                        @error('expiresAt') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="ag-form__actions">
                    <button type="submit" class="ag-btn ag-btn--primary">{{ __('common.save') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelCode">{{ __('common.cancel') }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="admin-dashboard">
        <section class="ag-stats" aria-label="{{ __('admin.referrals.stats_label') }}">
            <div class="ag-stats__item">
                <p class="ag-stats__label">{{ __('admin.referrals.active_codes') }}</p>
                <p class="ag-stats__value">{{ $activeCodeCount }}</p>
                <p class="ag-stats__hint">{{ __('admin.referrals.active_codes_hint') }}</p>
            </div>
            <div class="ag-stats__item">
                <p class="ag-stats__label">{{ __('admin.referrals.needs_review') }}</p>
                <p class="ag-stats__value">{{ $reviewCount }}</p>
                <p class="ag-stats__hint">{{ __('admin.referrals.needs_review_hint') }}</p>
            </div>
            <div class="ag-stats__item">
                <p class="ag-stats__label">{{ __('admin.referrals.posted_rewards') }}</p>
                <p class="ag-stats__value">{{ $postedRewardCount }}</p>
                <p class="ag-stats__hint">{{ __('admin.referrals.posted_rewards_hint') }}</p>
            </div>
        </section>

        <section class="ag-card" aria-labelledby="referral-codes-heading">
            <header class="ag-card__header">
                <h2 id="referral-codes-heading" class="ag-card__title">{{ __('admin.referrals.codes_title') }}</h2>
                <p class="ag-card__description">{{ __('admin.referrals.codes_description') }}</p>
            </header>
            <div class="ag-card__content">
                <div class="ag-table-wrap">
                    <table class="admin-table">
                        <caption class="sr-only">{{ __('admin.referrals.codes_title') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('admin.referrals.code') }}</th>
                                <th scope="col">{{ __('admin.referrals.customer') }}</th>
                                <th scope="col">{{ __('admin.referrals.uses') }}</th>
                                <th scope="col">{{ __('admin.referrals.status') }}</th>
                                <th scope="col">{{ __('admin.referrals.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($codes as $code)
                                <tr>
                                    <td><strong>{{ $code->code }}</strong></td>
                                    <td>{{ $code->customer?->name ?? __('admin.referrals.not_available') }}</td>
                                    <td>{{ $code->uses_count }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}</td>
                                    <td>
                                        <span class="ag-badge {{ $code->is_active ? 'ag-badge--success' : 'ag-badge--muted' }}">
                                            {{ $code->is_active ? __('admin.referrals.active') : __('admin.referrals.inactive') }}
                                        </span>
                                    </td>
                                    <td>
                                        @can('referrals.manage')
                                            @if ($code->is_active)
                                                <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="deactivateCode({{ $code->id }})" wire:loading.attr="disabled">
                                                    {{ __('admin.referrals.deactivate') }}
                                                </button>
                                            @else
                                                <button type="button" class="ag-btn ag-btn--primary ag-btn--sm" wire:click="activateCode({{ $code->id }})" wire:loading.attr="disabled">
                                                    {{ __('admin.referrals.activate') }}
                                                </button>
                                            @endif
                                        @else
                                            <span class="ag-muted">{{ __('admin.referrals.read_only') }}</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">{{ __('admin.referrals.codes_empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="ag-card" aria-labelledby="referral-attributions-heading">
            <header class="ag-card__header">
                <h2 id="referral-attributions-heading" class="ag-card__title">{{ __('admin.referrals.attributions_title') }}</h2>
                <p class="ag-card__description">{{ __('admin.referrals.attributions_description') }}</p>
            </header>
            <div class="ag-card__content">
                <div class="ag-table-wrap">
                    <table class="admin-table">
                        <caption class="sr-only">{{ __('admin.referrals.attributions_title') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('admin.referrals.code') }}</th>
                                <th scope="col">{{ __('admin.referrals.order') }}</th>
                                <th scope="col">{{ __('admin.referrals.status') }}</th>
                                <th scope="col">{{ __('admin.referrals.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($attributions as $attribution)
                                @php
                                    $statusClass = match ($attribution->status) {
                                        'review' => 'ag-badge--warning',
                                        'posted' => 'ag-badge--success',
                                        'void' => 'ag-badge--danger',
                                        default => 'ag-badge--muted',
                                    };
                                @endphp
                                <tr>
                                    <td><strong>{{ $attribution->code_snapshot }}</strong></td>
                                    <td>{{ $attribution->order?->number ?? __('admin.referrals.not_available') }}</td>
                                    <td>
                                        <span class="ag-badge {{ $statusClass }}">
                                            {{ __('admin.referrals.statuses.'.$attribution->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        @can('referrals.manage')
                                            @if ($attribution->status === 'review')
                                                <div class="admin-page__actions">
                                                    <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="approve({{ $attribution->id }})" wire:loading.attr="disabled">
                                                        {{ __('admin.referrals.approve') }}
                                                    </button>
                                                    <button type="button" class="ag-btn ag-btn--danger-outline ag-btn--sm" wire:click="reject({{ $attribution->id }})" wire:loading.attr="disabled">
                                                        {{ __('admin.referrals.reject') }}
                                                    </button>
                                                </div>
                                            @else
                                                <span class="ag-muted">{{ __('admin.referrals.no_action') }}</span>
                                            @endif
                                        @else
                                            <span class="ag-muted">{{ __('admin.referrals.read_only') }}</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4">{{ __('admin.referrals.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
