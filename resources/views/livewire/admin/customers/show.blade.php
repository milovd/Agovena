@php
    $nameParts = collect(preg_split('/\s+/', trim($customer->name)) ?: [])->filter();
    $initials = $nameParts->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $formatMoney = static fn (int $amount, string $currency): string => \App\Support\MoneyFormatter::format($amount, $currency);
@endphp

<div class="admin-page customer-workspace">
    <x-ag.page-header :heading="$customer->name" :lede="$customer->email">
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.customers.title'), 'url' => route('admin.customers.index')],
                ['label' => $customer->name],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.customers.index')" :label="__('admin.customers.back')" />
        </x-slot:back>
        <x-slot:actions>
            @if ($customer->anonymized_at)
                <span class="ag-badge">{{ __('admin.customers.anonymized_badge') }}</span>
            @elseif ($customer->deletion_requested_at)
                <span class="ag-badge ag-badge--warning">{{ __('admin.customers.deletion_requested_badge') }}</span>
            @endif
            @if ($user?->hasVerifiedEmail())
                <span class="ag-badge ag-badge--success">{{ __('admin.customers.email_verified') }}</span>
            @else
                <span class="ag-badge ag-badge--warning">{{ __('admin.customers.email_unverified') }}</span>
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <section class="customer-workspace__hero" aria-label="{{ __('admin.customers.workspace_eyebrow') }}">
        <div class="customer-workspace__identity">
            <span class="customer-workspace__avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
            <div>
                <p class="customer-workspace__eyebrow">{{ __('admin.customers.workspace_eyebrow') }}</p>
                <p class="customer-workspace__meta">
                    {{ __('admin.customers.customer_since', ['date' => $customer->created_at?->format('d M Y')]) }}
                    <span aria-hidden="true">·</span>
                    {{ __('admin.customers.user_id') }} #{{ $user?->id ?? '-' }}
                </p>
            </div>
        </div>
        <div class="customer-workspace__hero-status">
            <span class="customer-workspace__hero-status-label">{{ __('admin.customers.account_status') }}</span>
            <strong>{{ $customer->anonymized_at ? __('admin.customers.anonymized_badge') : __('admin.customers.status_active') }}</strong>
        </div>
    </section>

    <div class="customer-workspace__metrics" aria-label="{{ __('admin.customers.summary_aria') }}">
        <article class="customer-workspace__metric">
            <x-ag.icon-tile name="shopping-bag" tone="blue" :size="18" class="customer-workspace__metric-icon" />
            <span class="customer-workspace__metric-label">{{ __('admin.orders.title') }}</span>
            <strong class="customer-workspace__metric-value">{{ $stats['orders'] }}</strong>
            <span class="customer-workspace__metric-note">{{ __('admin.customers.summary_orders') }}</span>
        </article>
        <article class="customer-workspace__metric">
            <x-ag.icon-tile name="file-text" tone="violet" :size="18" class="customer-workspace__metric-icon" />
            <span class="customer-workspace__metric-label">{{ __('admin.invoices.title') }}</span>
            <strong class="customer-workspace__metric-value">{{ $stats['invoices'] }}</strong>
            <span class="customer-workspace__metric-note">{{ __('admin.customers.summary_invoices') }}</span>
        </article>
        <article class="customer-workspace__metric">
            <x-ag.icon-tile name="ticket" tone="amber" :size="18" class="customer-workspace__metric-icon" />
            <span class="customer-workspace__metric-label">{{ __('admin.tickets.title') }}</span>
            <strong class="customer-workspace__metric-value">{{ $stats['tickets'] }}</strong>
            <span class="customer-workspace__metric-note">{{ __('admin.customers.summary_tickets') }}</span>
        </article>
        <article class="customer-workspace__metric">
            <x-ag.icon-tile name="wallet" tone="emerald" :size="18" class="customer-workspace__metric-icon" />
            <span class="customer-workspace__metric-label">{{ __('admin.customers.credit_heading') }}</span>
            <strong class="customer-workspace__metric-value">{{ $formatMoney($balanceAmount, $currency) }}</strong>
            <span class="customer-workspace__metric-note">{{ __('admin.customers.summary_credit') }}</span>
        </article>
    </div>

    <div class="customer-workspace__layout">
        <main class="customer-workspace__main">
            @include('livewire.admin.customers.partials.show-profile')

            @include('livewire.admin.customers.partials.show-activity')

            <section class="customer-workspace__card" aria-labelledby="capabilities-heading">
                <header class="customer-workspace__section-header">
                    <x-ag.icon name="puzzle" :size="20" class="customer-workspace__section-icon" />
                    <div>
                        <p class="customer-workspace__eyebrow">{{ __('admin.customers.capabilities_eyebrow') }}</p>
                        <h2 id="capabilities-heading" class="customer-workspace__section-title">{{ __('admin.customers.capabilities_heading') }}</h2>
                        <p class="customer-workspace__section-lede">{{ __('admin.customers.capabilities_lede') }}</p>
                    </div>
                </header>
                <div class="customer-workspace__capabilities">
                    @forelse ($customerDetailSections ?? [] as $section)
                        @if ($section->permission === null || auth()->user()?->can($section->permission))
                            @livewire($section->component, ['customer' => $customer], key($section->id.'-'.$customer->id))
                        @endif
                    @empty
                        <p class="customer-workspace__empty">{{ __('admin.customers.no_capability_sections') }}</p>
                    @endforelse
                </div>
            </section>
        </main>

        <aside class="customer-workspace__sidebar">
            @include('livewire.admin.customers.partials.show-security')

            @include('livewire.admin.customers.partials.show-credits')

            @include('livewire.admin.customers.partials.show-access')

            @include('livewire.admin.customers.partials.show-danger-zone')
        </aside>
    </div>

    @include('livewire.admin.partials.confirm-password-modal')
</div>