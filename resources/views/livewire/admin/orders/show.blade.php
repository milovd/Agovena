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
        </div>
        <x-slot:actions>
            @can('orders.update')
                <a class="ag-icon-btn ag-icon-btn--header" href="{{ route('admin.orders.edit', $order) }}" title="{{ __('admin.orders.actions.edit') }}" aria-label="{{ __('admin.orders.actions.edit_aria', ['number' => $order->number]) }}">
                    <x-ag.icon name="pencil" :size="18" />
                </a>
            @endcan
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
            <x-ag.metric-card
                icon="user"
                tone="amber"
                :label="__('common.customer')"
                :value="$order->customer_name"
                :href="'mailto:'.$order->customer_email"
                :link-label="$order->customer_email"
            />
        </div>

        <div class="ag-order-layout__main">
            @include('livewire.admin.orders.partials.show-items')

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

            @include('livewire.admin.orders.partials.show-documents')
        </aside>
    </div>

    @include('livewire.admin.partials.confirm-password-modal')
</div>
