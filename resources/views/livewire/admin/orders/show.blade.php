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
        <x-slot:actions>
            @if ($canUpdate)
                <button type="button" class="ag-icon-btn ag-icon-btn--header" wire:click="startEditDetails" title="{{ __('admin.orders.inline.edit_details') }}" aria-label="{{ __('admin.orders.inline.edit_details_aria', ['number' => $order->number]) }}">
                    <x-ag.icon name="pencil" :size="18" />
                </button>
            @endif
            @if ($canDelete)
                <div class="ag-menu" x-data="agDisclosure" @keydown.escape.window="close()" @click.outside="close()">
                    <button type="button" class="ag-icon-btn ag-icon-btn--header" @click="toggle()" :aria-expanded="open.toString()" aria-haspopup="menu" title="{{ __('admin.orders.actions.more') }}" aria-label="{{ __('admin.orders.actions.more_aria', ['number' => $order->number]) }}">
                        <x-ag.icon name="more-horizontal" :size="18" />
                    </button>
                    <div class="ag-menu__panel" x-show="open" x-cloak role="menu">
                        <button type="button" class="ag-menu__item ag-menu__item--danger" role="menuitem" wire:click="confirmDelete">{{ __('admin.orders.actions.delete') }}</button>
                    </div>
                </div>
            @endif
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

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
                            <a class="admin-order__customer-link" href="{{ route('admin.customers.show', $order->customer_id) }}">{{ __('admin.orders.inline.go_to_customer') }} <x-ag.icon name="arrow-right" :size="14" /></a>
                        @endcan
                    @endif
                </p>
            </x-ag.metric-card>
        </div>

        <div class="ag-order-layout__main">
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

    @include('livewire.admin.partials.confirm-password-modal')

    @if ($editingDetails && $canUpdate)
        <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="order-details-title" wire:keydown.escape="cancelEditDetails">
            <div class="ag-modal__backdrop" wire:click="cancelEditDetails"></div>
            <form class="ag-modal__panel ag-form admin-order__details-form" wire:submit="saveDetails" novalidate>
                <h2 id="order-details-title" class="ag-modal__title">{{ __('admin.orders.inline.edit_details') }}</h2>
                @error('order') <p class="ag-alert ag-alert--danger" role="alert">{{ $message }}</p> @enderror

                <div class="ag-field ag-combobox">
                    <div class="admin-order__field-head">
                        <label class="ag-field__label" for="order-customer-search">{{ __('admin.orders.inline.customer') }}</label>
                        @if ($order->customer_id !== null)
                            @can('customers.view')
                                <a href="{{ route('admin.customers.show', $order->customer_id) }}">{{ __('admin.orders.inline.go_to_customer') }}</a>
                            @endcan
                        @endif
                    </div>
                    @if ($customerLockReason === null)
                        <div class="ag-combobox__control">
                            <input id="order-customer-search" class="ag-input" type="search" wire:model.live.debounce.300ms="customerSearch" role="combobox" aria-autocomplete="list" aria-controls="order-customer-options" aria-expanded="{{ $customerResults->isNotEmpty() ? 'true' : 'false' }}" placeholder="{{ __('admin.orders.inline.customer_placeholder') }}" autocomplete="off">
                            @if ($customerResults->isNotEmpty())
                                <div id="order-customer-options" class="ag-combobox__options" role="listbox">
                                    @foreach ($customerResults as $result)
                                        <button type="button" class="ag-combobox__option" role="option" wire:click="selectCustomer({{ $result->id }})" wire:key="order-customer-{{ $result->id }}">
                                            <span>{{ $result->name }} ({{ $result->email }})</span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (trim($customerSearch) !== '' && $selectedCustomerId === null)
                                <p class="ag-combobox__empty">{{ __('admin.orders.inline.no_customers') }}</p>
                            @endif
                        </div>
                        <p class="ag-field__help">{{ __('admin.orders.inline.customer_change_help') }}</p>
                    @else
                        <div id="order-customer-search" class="ag-input ag-input--static">{{ $order->customer_id !== null ? $order->customer_name.' ('.$order->customer_email.')' : __('admin.orders.inline.guest') }}</div>
                        <p class="ag-field__help">{{ $customerLockReason }}</p>
                    @endif
                </div>

                <div class="ag-field">
                    <label class="ag-field__label" for="order-customer-name">{{ __('common.name') }}</label>
                    <input id="order-customer-name" class="ag-input" type="text" wire:model="details.customerName" autocomplete="off">
                    @error('details.customerName') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-customer-email">{{ __('common.email') }}</label>
                    <input id="order-customer-email" class="ag-input" type="email" wire:model="details.customerEmail" autocomplete="off">
                    <p class="ag-field__help">{{ __('admin.orders.inline.snapshot_help') }}</p>
                    @error('details.customerEmail') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-field">
                    <label class="ag-field__label" for="order-due-at">{{ __('admin.orders.edit.due_at') }}</label>
                    <x-ag.date-input id="order-due-at" class="ag-input" type="datetime-local" wire:model="details.dueAt" />
                    @error('details.dueAt') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="ag-modal__actions">
                    <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="saveDetails">{{ __('common.save') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelEditDetails">{{ __('common.cancel') }}</button>
                </div>
            </form>
        </div>
    @endif

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
