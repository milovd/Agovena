<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <p class="admin-page__eyebrow">{{ __('admin.referrals.eyebrow') }}</p>
            <h1 class="admin-page__title">{{ __('admin.referrals.title') }}</h1>
            <p class="admin-page__lede">{{ __('admin.referrals.lede') }}</p>
        </div>
    </header>

    <section class="admin-table-wrap" aria-labelledby="referral-codes-heading">
        <h2 id="referral-codes-heading" class="admin-section-title">{{ __('admin.referrals.codes_title') }}</h2>
        <table class="admin-table">
            <caption class="sr-only">{{ __('admin.referrals.codes_title') }}</caption>
            <thead><tr><th>{{ __('admin.referrals.code') }}</th><th>{{ __('admin.referrals.customer') }}</th><th>{{ __('admin.referrals.uses') }}</th><th>{{ __('admin.referrals.active') }}</th><th>{{ __('admin.referrals.actions') }}</th></tr></thead>
            <tbody>
                @forelse ($codes as $code)
                    <tr>
                        <td>{{ $code->code }}</td>
                        <td>{{ $code->customer?->name ?? 'n/a' }}</td>
                        <td>{{ $code->uses_count }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}</td>
                        <td>{{ $code->is_active ? __('admin.referrals.active') : __('admin.referrals.inactive') }}</td>
                        <td>
                            @if ($code->is_active)
                                <button type="button" wire:click="deactivateCode({{ $code->id }})">{{ __('admin.referrals.deactivate') }}</button>
                            @else
                                <button type="button" wire:click="activateCode({{ $code->id }})">{{ __('admin.referrals.activate') }}</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">{{ __('admin.referrals.codes_empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="admin-table-wrap" aria-labelledby="referral-attributions-heading">
        <h2 id="referral-attributions-heading" class="admin-section-title">{{ __('admin.referrals.attributions_title') }}</h2>
        <table class="admin-table">
            <caption class="sr-only">{{ __('admin.referrals.attributions_title') }}</caption>
            <thead><tr><th>{{ __('admin.referrals.code') }}</th><th>{{ __('admin.referrals.order') }}</th><th>{{ __('admin.referrals.status') }}</th><th>{{ __('admin.referrals.actions') }}</th></tr></thead>
            <tbody>
                @forelse ($attributions as $attribution)
                    <tr>
                        <td>{{ $attribution->code_snapshot }}</td>
                        <td>{{ $attribution->order?->number ?? 'n/a' }}</td>
                        <td>{{ $attribution->status }}</td>
                        <td>
                            @if ($attribution->status === 'review')
                                <button type="button" wire:click="approve({{ $attribution->id }})">{{ __('admin.referrals.approve') }}</button>
                                <button type="button" wire:click="reject({{ $attribution->id }})">{{ __('admin.referrals.reject') }}</button>
                            @else
                                <span>{{ __('admin.referrals.no_action') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">{{ __('admin.referrals.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
