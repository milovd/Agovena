<div class="admin-page">
    <header class="admin-page__header">
        <div>
            <p class="admin-page__eyebrow">{{ __('admin.referrals.eyebrow') }}</p>
            <h1 class="admin-page__title">{{ __('admin.referrals.title') }}</h1>
            <p class="admin-page__lede">{{ __('admin.referrals.lede') }}</p>
        </div>
    </header>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <caption class="sr-only">{{ __('admin.referrals.title') }}</caption>
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
    </div>
</div>
