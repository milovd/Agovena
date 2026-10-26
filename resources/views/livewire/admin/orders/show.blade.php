@php
    $money = static fn (int $amount, string $currency): string => \App\Support\MoneyFormatter::format($amount, $currency);
    // One combined status, the same as the order list. The Payment card keeps the separate payment status.
    $listStatus = \App\Agovena\Orders\OrderListStatus::for($order);
    $itemQuantity = (int) $order->items->sum('quantity');
    $lineCount = $order->items->count();
@endphp

<div class="admin-page admin-page--order">
    <x-ag.page-header :heading="$order->number">
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.orders.title'), 'url' => route('admin.orders.index')],
                ['label' => $order->number],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.orders.index')" :label="__('admin.orders.title')" />
        </x-slot:back>
        <div class="admin-order__summary">
            <span class="ag-badge ag-badge--{{ $listStatus->badgeVariant() }}">{{ __('admin.orders.list_status.'.$listStatus->value) }}</span>
            <span>{{ __('common.created') }} {{ $order->created_at?->toDayDateTimeString() }}</span>
            @if ($order->due_at)
                <span>{{ __('admin.orders.inline.due', ['date' => $order->due_at->toFormattedDateString()]) }}</span>
            @endif
        </div>
        @if ($canDelete)
            <x-slot:actions>
                <div class="ag-menu" x-data="agDisclosure" @keydown.escape.window="close()" @click.outside="close()">
                    <button type="button" class="ag-icon-btn ag-icon-btn--header" @click="toggle()" :aria-expanded="open.toString()" aria-haspopup="menu" title="{{ __('admin.orders.actions.more') }}" aria-label="{{ __('admin.orders.actions.more_aria', ['number' => $order->number]) }}">
                        <x-ag.icon name="more-horizontal" :size="18" />
                    </button>
                    <div class="ag-menu__panel" x-show="open" x-cloak role="menu">
                        <button type="button" class="ag-menu__item ag-menu__item--danger" role="menuitem" wire:click="confirmDelete">
                            <x-ag.icon name="trash" :size="15" /> {{ __('admin.orders.actions.delete') }}
                        </button>
                    </div>
                </div>
            </x-slot:actions>
        @endif
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif
    @error('order')
        <p class="ag-alert ag-alert--danger" role="alert">{{ $message }}</p>
    @enderror

    <div class="ag-order-layout">
        {{-- The metrics share the layout columns: Total and Items span the main column, Customer matches Payment. --}}
        <div class="ag-metrics admin-order__metrics admin-order__metrics--main" role="list" aria-label="{{ __('admin.orders.show.summary') }}">
            <x-ag.metric-card
                icon="wallet"
                tone="emerald"
                :label="__('common.total')"
                :value="$money($order->total_amount, $order->currency)"
                :hint="__('common.subtotal').' '.$money($order->subtotal_amount, $order->currency)"
            />
            <x-ag.metric-card
                icon="package"
                tone="blue"
                :label="__('admin.orders.show.items')"
                :value="(string) $itemQuantity"
                :hint="$lineCount !== $itemQuantity ? trans_choice('admin.orders.show.lines', $lineCount, ['count' => $lineCount]) : null"
            />
        </div>
        <div class="ag-metrics admin-order__metrics admin-order__metrics--side" role="list" aria-label="{{ __('common.customer') }}">
            <x-ag.metric-card class="admin-order__customer" icon="user" tone="amber" :label="__('common.customer')" :value="$order->customer_name">
                <p class="admin-order__customer-links">
                    <a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a>
                    @if ($order->customer_id === null)
                        <span class="ag-muted">{{ __('admin.orders.inline.guest') }}</span>
                    @else
                        @can('customers.view')
                            <a class="admin-order__customer-link" href="{{ route('admin.customers.show', $order->customer_id) }}" title="{{ __('admin.orders.inline.go_to_customer') }}" aria-label="{{ __('admin.orders.inline.go_to_customer') }}"><x-ag.icon name="external-link" :size="15" /></a>
                        @endcan
                    @endif
                </p>
            </x-ag.metric-card>
        </div>

        <div class="ag-order-layout__main">
            @include('livewire.admin.orders.partials.show-details')

            @include('livewire.admin.orders.partials.show-items')

            @include('livewire.admin.orders.partials.show-invoices')

            @include('livewire.admin.orders.partials.show-addresses')

            @if ($displayableProperties !== [])
                <section class="ag-card" aria-labelledby="order-properties-heading">
                    <header class="ag-card__header">
                        <x-ag.icon-tile name="user" tone="indigo" />
                        <h2 id="order-properties-heading" class="ag-card__title">{{ __('admin.customer_properties.values_heading') }}</h2>
                    </header>
                    <div class="ag-card__content">
                        <dl class="ag-dl">
                            @foreach ($displayableProperties as $property)
                                <div>
                                    <dt>{{ $property['label'] ?? $property['key'] }}</dt>
                                    <dd>{{ $property['value'] ?? '' }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>
            @endif

            @foreach ($orderDetailSections ?? [] as $section)
                @livewire($section->component, ['order' => $order], key($section->id.'-'.$order->id))
            @endforeach
        </div>

        <aside class="ag-order-layout__side">
            @include('livewire.admin.orders.partials.show-payments')
        </aside>
    </div>

    @if ($canUpdate)
        {{-- Paymenter-style form: one save for everything on the page, visible once something changed. --}}
        <div @class(['admin-order__savebar', 'is-dirty' => $isDirty]) wire:dirty.class="is-visible" role="region" aria-label="{{ __('admin.orders.inline.unsaved') }}">
            <span class="admin-order__savebar-text">{{ __('admin.orders.inline.unsaved') }}</span>
            <button type="button" class="ag-icon-btn" wire:click="resetForm" title="{{ __('admin.orders.inline.reset') }}" aria-label="{{ __('admin.orders.inline.reset') }}">
                <x-ag.icon name="rotate-ccw" :size="16" />
            </button>
            <button type="button" class="ag-btn ag-btn--primary" wire:click="saveOrder" wire:loading.attr="disabled" wire:target="saveOrder">
                <x-ag.icon name="check" :size="16" /> {{ __('common.save') }}
            </button>
        </div>
    @endif

    @include('livewire.admin.partials.confirm-password-modal')

    @if ($confirmingDelete && $canDelete)
        <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="delete-order-title">
            <div class="ag-modal__backdrop" wire:click="cancelDelete"></div>
            <div class="ag-modal__panel">
                <h2 id="delete-order-title" class="ag-modal__title">{{ __('admin.orders.delete.title', ['number' => $order->number]) }}</h2>
                <p class="ag-modal__text">{{ __('admin.orders.delete.text') }}</p>
                <div class="ag-modal__actions">
                    <button type="button" class="ag-btn ag-btn--danger" wire:click="deleteOrder">{{ __('admin.orders.actions.delete') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelDelete">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
